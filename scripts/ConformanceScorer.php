<?php

declare(strict_types=1);

namespace Alama\Arazzo\Scripts;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Scores php-arazzo against the api-evangelist/arazzo-conformance suite.
 *
 * The scorer re-uses the suite's own case definitions
 * (cases/<set>/manifest.yml) rather than hard-coding cases: it loads every
 * validation and execution
 * case, runs each one against vendor/bin/arazzi, and writes a machine
 * readable conformance/score.json with per-case verdicts, versions and the
 * pinned suite SHA.
 *
 * Execution cases run against the suite's scripts/mock-petstore.py served on
 * 127.0.0.1:4010; the scorer starts/stops that mock itself.
 */
final class ConformanceScorer
{
    // Released Arazzo versions: declining any other version is the right answer.
    private const RELEASED = ['1.0.0', '1.0.1', '1.1.0'];

    // OAI marks these pass/ because the schema accepts them; the spec's prose does not.
    private const OAI_OVERRIDES = [
        'oai-1.0.1-pass-bnpl-example' => 'Uses $response.body.redirectAuthToken and $response.headers.Location; the runtime expression ABNF allows only $response.body#/<json-pointer> and $response.header.<name>.',
        'oai-1.0.1-pass-oauth-example' => 'A jsonpath criterion with condition "$.access_token != null" is not a JSONPath query; a comparison needs a filter selector.',
        'oai-1.0.1-pass-pet-coupons-example' => 'Sets a Reusable Object value to the integer 1 and 10; the 1.0.1 spec types value as a string.',
        'oai-1.1.0-pass-bnpl-example' => 'Uses $response.body.redirectAuthToken and $response.headers.Location; the runtime expression ABNF allows only $response.body#/<json-pointer> and $response.header.<name>.',
        'oai-1.1.0-pass-oauth-example' => 'A jsonpath criterion with condition "$.access_token != null" is not a JSONPath query; a comparison needs a filter selector.',
        'oai-1.1.0-pass-async-depends-on' => 'Uses $message.payload.userId; the ABNF allows only payload#/<json-pointer> after payload.',
        'oai-1.1.0-pass-async-depends-on-cross-workflow' => 'Uses $message.payload.<name>; the ABNF allows only payload#/<json-pointer> after payload.',
        'oai-1.1.0-pass-openapi-and-asyncapi' => 'Uses $message.payload.orderId; the ABNF allows only payload#/<json-pointer> after payload.',
    ];

    private const VALUE_CONTESTED = 'Contested, not scored: the 1.1.0 spec types a Reusable Object value as string, while OAI\'s 1.1.0 tests pass non-string values.';

    private const VALUE_CONTESTED_RE = '/^oai-1\.1\.0-pass-(reusable-parameter-(array|boolean|integer|null|number|object)-value|pet-coupons-example|openapi-parameter-reusable-reference|workflow-parameter-reusable-reference)$/';

    public function __construct(
        public readonly string $suiteDir,
        public string $binary,
        public readonly string $output,
        public readonly string $sha,
        public readonly int $mockPort = 4010,
        public readonly ?string $script = null,
    ) {
        // Resolve a relative binary against the repo root so it stays valid
        // once child processes are launched with a different cwd (the case
        // file's directory). getcwd() can point at a temp directory when the
        // scorer is invoked through a Process with a custom cwd, so fall back
        // to resolving relative to this script's directory (repo root).
        if (!str_starts_with($this->binary, '/') && !str_starts_with($this->binary, '\\')) {
            $resolved = realpath(getcwd().'/'.$this->binary);
            if ($resolved === false) {
                $resolved = realpath(__DIR__.'/../../'.$this->binary);
            }
            if ($resolved !== false) {
                $this->binary = $resolved;
            }
        }
    }

    /**
     * Build the command array for a given action.
     *
     * @return list<string>
     */
    private function command(string ...$args): array
    {
        $parts = [$this->binary];
        if ($this->script !== null) {
            $parts[] = $this->script;
        }

        return array_merge($parts, $args);
    }

    /**
     * Load every case, run validation + execution, write score.json.
     *
     * @return array{suite: string, sha: string, generatedAt: string, mockPort: int, validation: array, execution: array}
     */
    public function score(): array
    {
        $cases = $this->loadCases();
        $validation = $this->scoreValidationCases($cases['validation']);
        $execution = $this->scoreExecutionCases($cases['execution']);

        $result = [
            'suite' => 'api-evangelist/arazzo-conformance',
            'sha' => $this->sha,
            'generatedAt' => date('c'),
            'mockPort' => $this->mockPort,
            'validation' => [
                'total' => count(array_filter($validation['cases'], static fn (array $c): bool => !$c['contested'])),
                'passed' => count(array_filter($validation['cases'], static fn (array $c): bool => $c['pass'] === true)),
                'cases' => $validation['cases'],
            ],
            'execution' => [
                'total' => count(array_filter($execution['cases'], static fn (array $c): bool => !$c['contested'])),
                'passed' => count(array_filter($execution['cases'], static fn (array $c): bool => $c['pass'] === true)),
                'cases' => $execution['cases'],
            ],
        ];

        file_put_contents($this->output, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        return $result;
    }

    /**
     * @return array{validation: list<array>, execution: list<array>}
     */
    private function loadCases(): array
    {
        $validation = [];
        $execution = [];

        // API Evangelist validation cases (one valid base + one mutation each).
        foreach (glob($this->suiteDir.'/cases/ae/*/manifest.yml') ?: [] as $manifest) {
            $dir = dirname($manifest);
            $data = Yaml::parseFile($manifest);
            $version = $this->manifestVersion($dir, $data);

            foreach ((array) (($data['cases'] ?? [])) as $case) {
                $validation[] = $this->validationCase(
                    (string) ($case['id'] ?? ''),
                    'ae',
                    $version,
                    (string) ($case['level'] ?? 'semantic'),
                    (string) ($case['expect'] ?? 'valid'),
                    (string) ($case['rule'] ?? ''),
                    (string) ($case['spec'] ?? ''),
                    (string) ($case['file'] ?? ''),
                    $dir,
                );
            }
        }

        // Official OAI schema tests: pass/ = valid, fail/ = invalid.
        foreach (glob($this->suiteDir.'/cases/oai/*/*/*.yaml') ?: [] as $path) {
            $parts = explode('/', str_replace('\\', '/', $path));
            $version = $parts[count($parts) - 3] ?? '';
            $kind = $parts[count($parts) - 2] ?? '';
            $name = basename($path, '.yaml');
            $name = str_replace('.arazzo', '', $name);
            $charac = str_starts_with($name, 'characterization-');
            $id = 'oai-'.$version.'-'.$kind.'-'.$name;
            $expect = ($kind === 'fail' || $charac) ? 'invalid' : 'valid';
            $level = $charac ? 'semantic' : 'schema';
            $note = null;

            if (isset(self::OAI_OVERRIDES[$id])) {
                $expect = 'invalid';
                $level = 'semantic';
                $note = self::OAI_OVERRIDES[$id];
            } elseif (preg_match(self::VALUE_CONTESTED_RE, $id)) {
                $expect = 'contested';
                $note = self::VALUE_CONTESTED;
            }

            $validation[] = [
                'id' => $id,
                'suite' => 'oai',
                'version' => $version,
                'level' => $level,
                'expect' => $expect,
                'rule' => str_replace('-', ' ', $name),
                'spec' => 'https://spec.openapis.org/arazzo/v'.$version.'.html',
                'file' => $name.'.yaml',
                'path' => $path,
                'note' => $note,
                'contested' => $expect === 'contested',
            ];
        }

        // Execution cases: whole workflows run against mock-petstore.py on :4010.
        foreach (glob($this->suiteDir.'/cases/exec/*/manifest.yml') ?: [] as $manifest) {
            $dir = dirname($manifest);
            $data = Yaml::parseFile($manifest);
            $version = $this->manifestVersion($dir, $data);

            foreach ((array) (($data['cases'] ?? [])) as $case) {
                $execution[] = [
                    'id' => (string) ($case['id'] ?? ''),
                    'version' => $version,
                    'workflow' => (string) ($case['workflow'] ?? ''),
                    'inputs' => (array) ($case['inputs'] ?? []),
                    'expect' => (string) ($case['expect'] ?? 'success'),
                    'requests' => (array) ($case['requests'] ?? []),
                    'match' => (array) ($case['match'] ?? []),
                    'rule' => (string) ($case['rule'] ?? ''),
                    'spec' => (string) ($case['spec'] ?? ''),
                    'file' => (string) ($case['file'] ?? ''),
                    'path' => $dir.'/'.$case['file'],
                    'contested' => (string) ($case['expect'] ?? '') === 'contested',
                ];
            }
        }

        return ['validation' => $validation, 'execution' => $execution];
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $suite
     * @param  mixed  $version
     * @param  mixed  $level
     * @param  mixed  $expect
     * @param  mixed  $rule
     * @param  mixed  $spec
     * @param  mixed  $file
     * @param  mixed  $dir
     */
    private function validationCase($id, $suite, $version, $level, $expect, $rule, $spec, $file, $dir): array
    {
        return [
            'id' => (string) $id,
            'suite' => (string) $suite,
            'version' => (string) $version,
            'level' => (string) $level,
            'expect' => (string) $expect,
            'rule' => (string) $rule,
            'spec' => (string) $spec,
            'file' => (string) $file,
            'path' => $dir.'/'.$file,
            'contested' => (string) $expect === 'contested',
        ];
    }

    /**
     * @param  list<array>  $cases
     * @return array{total: int, passed: int, cases: list<array>}
     */
    private function scoreValidationCases(array $cases): array
    {
        $out = [];

        foreach ($cases as $case) {
            $verdict = $this->runValidate($case['path']);
            $pass = $case['contested'] ? null : $verdict === $case['expect'];

            $out[] = array_merge($case, [
                'verdict' => $verdict,
                'pass' => $pass,
            ]);
        }

        return ['total' => count($cases), 'passed' => count(array_filter($out, static fn (array $c): bool => $c['pass'] === true)), 'cases' => $out];
    }

    /**
     * @param  list<array>  $cases
     * @return array{total: int, passed: int, cases: list<array>}
     */
    private function scoreExecutionCases(array $cases): array
    {
        if ($cases === []) {
            return ['total' => 0, 'passed' => 0, 'cases' => []];
        }

        $mockScript = $this->suiteDir.'/scripts/mock-petstore.py';
        if (!is_file($mockScript)) {
            return ['total' => count($cases), 'passed' => 0,
                'cases' => array_map(
                    static fn (array $c): array => array_merge($c, ['verdict' => 'unscored', 'pass' => null, 'why' => 'no mock-petstore.py shipped']),
                    $cases,
                )];
        }

        $mock = $this->startMock($mockScript);
        try {
            $out = [];
            foreach ($cases as $case) {
                $judged = $this->judgeExecution($case);
                $out[] = array_merge($case, $judged);
            }

            return ['total' => count($cases), 'passed' => count(array_filter($out, static fn (array $c): bool => $c['pass'] === true)), 'cases' => $out];
        } finally {
            $mock->stop();
        }
    }

    private function runValidate(string $path): string
    {
        $process = new Process($this->command('validate', $path), dirname($path));
        $process->setTimeout(120);
        $process->run();

        return $process->getExitCode() === 0 ? 'valid' : 'invalid';
    }

    /**
     * @param  array<string, mixed>  $case
     * @return array{verdict: string, pass: bool|null, why?: string, outcome?: string, requests?: list<string>}
     */
    private function judgeExecution(array $case): array
    {
        $this->resetMock();
        $inputs = json_encode((array) ($case['inputs'] ?? []), JSON_THROW_ON_ERROR);

        $process = new Process(
            $this->command('run', $case['path'], '--workflow', (string) ($case['workflow'] ?? ''), '--input', $inputs),
            dirname($case['path']),
        );
        $process->setTimeout(120);
        $process->run();

        $exit = $process->getExitCode();
        $outcome = $exit === 0 ? 'success' : 'failure';
        $log = $this->fetchLog();
        $seen = array_map(
            static fn (array $r): string => (string) ($r['method'] ?? '').' '.(string) ($r['path'] ?? ''),
            $log,
        );

        $why = [];
        $pass = true;

        if (!($case['contested'] ?? false) && $case['expect'] !== 'any' && $outcome !== $case['expect']) {
            $pass = false;
            $why[] = 'outcome '.$outcome.', expected '.$case['expect'];
        }

        if ($seen !== (array) ($case['requests'] ?? [])) {
            $pass = false;
            $why[] = 'requests '.json_encode($seen).', expected '.json_encode($case['requests'] ?? []);
        }

        foreach ((array) ($case['match'] ?? []) as $index => $want) {
            $i = (int) $index;
            $got = isset($log[$i]) ? array_intersect_key($log[$i], array_flip(['body', 'query', 'headers'])) : [];
            if (!is_array($want) || !$this->subset((array) $want, $got)) {
                $pass = false;
                $why[] = 'request '.$i.' did not match '.json_encode($want);
            }
        }

        $scored = !($case['contested'] ?? false);

        return [
            'verdict' => $pass ? 'pass' : 'fail',
            'pass' => $scored ? $pass : null,
            'why' => implode('; ', $why),
            'outcome' => $outcome,
            'requests' => $seen,
            'exit' => $exit,
            'tail' => $process->getOutput(),
        ];
    }

    private function startMock(string $mockScript): Process
    {
        $finder = new ExecutableFinder();
        $python = $finder->find('python3') ?? 'python3';
        $process = new Process([$python, $mockScript, (string) $this->mockPort]);
        $process->setTimeout(120);
        $process->start();

        $url = 'http://127.0.0.1:'.$this->mockPort.'/__log';
        for ($i = 0; $i < 100; $i++) {
            @file_get_contents($url);
            if ($this->isMockReady($url)) {
                return $process;
            }
            usleep(50_000);
        }

        $process->stop();
        throw new \RuntimeException('mock petstore on port '.$this->mockPort.' did not become ready');
    }

    private function isMockReady(string $url): bool
    {
        $result = @file_get_contents($url);

        return $result !== false && json_decode($result, true) !== null;
    }

    private function resetMock(): void
    {
        $url = 'http://127.0.0.1:'.$this->mockPort.'/__reset';
        $context = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Length: 0', 'content' => '']]);
        @file_get_contents($url, false, $context);
    }

    /**
     * @return list<array>
     */
    private function fetchLog(): array
    {
        $url = 'http://127.0.0.1:'.$this->mockPort.'/__log';
        $json = @file_get_contents($url);
        $data = json_decode($json, true);

        return $data === null ? [] : $data;
    }

    /**
     * @param  array<string, mixed>  $want
     * @param  array<string, mixed>  $got
     */
    private function subset(array $want, array $got): bool
    {
        foreach ($want as $k => $v) {
            if (!array_key_exists($k, $got)) {
                return false;
            }
            if (is_array($v) && is_array($got[$k])) {
                if (!$this->subset($v, $got[$k])) {
                    return false;
                }
            } elseif ($v !== $got[$k]) {
                return false;
            }
        }

        return true;
    }

    private function manifestVersion(string $dir, array $data): string
    {
        foreach ($data['cases'] ?? [] as $case) {
            if (isset($case['version']) && is_string($case['version'])) {
                return $case['version'];
            }
        }

        $base = basename($dir);

        return preg_match('/^\d+\.\d+(\.\d+)?$/', $base) ? $base : '';
    }
}
