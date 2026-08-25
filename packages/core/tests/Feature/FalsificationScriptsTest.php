<?php

declare(strict_types=1);

$scripts = __DIR__ . '/../../../../.agents/skills/falsification-testing/scripts';
$scripts = realpath($scripts) ?: $scripts;

it('passes shell and php syntax for all scripts', function () use ($scripts): void {
    $bashScripts = ['hume-audit.sh', 'delete-fix-check.sh', 'verify-falsification.sh', 'test-scripts.sh'];
    foreach ($bashScripts as $f) {
        $out = [];
        $ec = 0;
        exec('bash -n ' . escapeshellarg("{$scripts}/{$f}") . ' 2>&1', $out, $ec);
        expect($ec)->toBe(0, implode("\n", $out));
    }

    $phpScripts = ['detect-fake-tests.php', 'audit-boundaries.php', 'scaffold-falsification-test.php'];
    foreach ($phpScripts as $f) {
        $out = [];
        $ec = 0;
        exec('php -l ' . escapeshellarg("{$scripts}/{$f}") . ' 2>&1', $out, $ec);
        expect($ec)->toBe(0, implode("\n", $out));
        expect(implode("\n", $out))->toContain('No syntax errors');
    }
});

it('detect-fake distinguishes fake from real fixtures', function () use ($scripts): void {
    $tmp = sys_get_temp_dir() . '/falsification-' . uniqid('', true);
    mkdir($tmp, 0777, true);

    $fake = "{$tmp}/fake.php";
    $real = "{$tmp}/real.php";

    file_put_contents($fake, <<<'PHP'
<?php declare(strict_types=1);
it('calls process', function () { $r=doThing(); expect($r)->not->toBeNull(); });
PHP);

    file_put_contents($real, <<<'PHP'
<?php declare(strict_types=1);
it('returns Retry when successCriteria false', function () {
  $r=Mockery::mock(ExpressionResolverInterface::class);
  [$c,$ok]=$executor->execute($step,$ctx,$doc);
  expect($ok)->toBeFalse();
});
it('handles empty workflows', function () { expect(fn()=> $parser->parse($empty))->toThrow(ParseException::class); });
PHP);

    $ecFake = 0;
    exec('php ' . escapeshellarg("{$scripts}/detect-fake-tests.php") . ' ' . escapeshellarg($fake) . ' 2>&1', $oFake, $ecFake);
    expect($ecFake)->toBe(1);
    expect(implode("\n", $oFake))->toContain('FAKE-1');

    $ecReal = 0;
    exec('php ' . escapeshellarg("{$scripts}/detect-fake-tests.php") . ' ' . escapeshellarg($real) . ' 2>&1', $oReal, $ecReal);
    expect($ecReal)->toBe(0);
    expect(implode("\n", $oReal))->toContain('No fake-test violations');

    // json emits FAKE-1 and NAMING
    $json = shell_exec('php ' . escapeshellarg("{$scripts}/detect-fake-tests.php") . ' ' . escapeshellarg($fake) . ' --json 2>&1');
    expect($json)->toContain('FAKE-1')->toContain('NAMING');

    // repo scan must not crash (exit 0 or 1, not 2)
    $ecAll = 0;
    exec('php ' . escapeshellarg("{$scripts}/detect-fake-tests.php") . ' --all 2>&1', $oAll, $ecAll);
    expect($ecAll)->toBeIn([0, 1]);

    exec('rm -rf ' . escapeshellarg($tmp));
});

it('audit-boundaries emits workflow engine checklist', function () use ($scripts): void {
    $json = shell_exec('php ' . escapeshellarg("{$scripts}/audit-boundaries.php") . ' WorkflowEngine --json 2>&1');
    expect($json)->not->toBeNull();
    $data = json_decode((string) $json, true);
    expect($data)->toHaveKey('boundaries');
    expect($data['boundaries'])->toContain('maxSteps at budget / stepsSpent==maxSteps');

    $text = shell_exec('php ' . escapeshellarg("{$scripts}/audit-boundaries.php") . ' packages/core/src/Validator/Validator.php 2>&1');
    expect($text)->toContain('Checklist');
});

it('scaffold generates pint-clean strict file', function () use ($scripts): void {
    $tmp = sys_get_temp_dir() . '/falsification-scaffold-' . uniqid('', true) . '.php';

    $ec = 0;
    exec('php ' . escapeshellarg("{$scripts}/scaffold-falsification-test.php") . ' core ScaffoldPestTest "harness claim" --dry-run 2>&1', $oDry, $ec);
    expect($ec)->toBe(0);
    expect(implode("\n", $oDry))->toContain('dry-run');

    $ec = 0;
    exec('php ' . escapeshellarg("{$scripts}/scaffold-falsification-test.php") . ' core ScaffoldPestTest "harness claim" --path ' . escapeshellarg($tmp) . ' 2>&1', $oWrite, $ec);
    expect($ec)->toBe(0);
    expect(file_exists($tmp))->toBeTrue();
    expect(file_get_contents($tmp))->toContain('declare(strict_types=1)');
    expect(file_get_contents($tmp))->toContain("it('harness-claim'");
    $ecLint = 0;
    exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $oLint, $ecLint);
    expect($ecLint)->toBe(0);

    // pint --test allows only single_blank_line_at_eof
    $pintOut = shell_exec('vendor/bin/pint ' . escapeshellarg($tmp) . ' --test 2>&1');
    if (str_contains((string) $pintOut, 'FAIL')) {
        expect($pintOut)->toContain('single_blank_line_at_eof');
    }

    unlink($tmp);
});

it('hume-audit and delete-fix expose --help and dry-run', function () use ($scripts): void {
    $humeHelp = shell_exec('bash ' . escapeshellarg("{$scripts}/hume-audit.sh") . ' --help 2>&1');
    expect($humeHelp)->toContain('usage');

    $humeDry = shell_exec('bash ' . escapeshellarg("{$scripts}/hume-audit.sh") . ' --dry-run --core 2>&1');
    expect($humeDry)->toContain('dry-run');

    $delHelp = shell_exec('bash ' . escapeshellarg("{$scripts}/delete-fix-check.sh") . ' --help 2>&1');
    expect($delHelp)->toContain('usage');

    $verifyHelp = shell_exec('bash ' . escapeshellarg("{$scripts}/verify-falsification.sh") . ' --help 2>&1');
    expect($verifyHelp)->toContain('usage');
});

it('self-test harness passes', function () use ($scripts): void {
    $ec = 0;
    exec('bash ' . escapeshellarg("{$scripts}/test-scripts.sh") . ' 2>&1', $out, $ec);
    expect($ec)->toBe(0, implode("\n", $out));
    expect(implode("\n", $out))->toContain('all self-tests passed');
});
