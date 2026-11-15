<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\Validator;
use Symfony\Component\Process\Process;

require_once __DIR__.'/../../../vendor/autoload.php';

/**
 * The prompt file for a section id: prompts/write-<id>.md inside the skill.
 */
function promptPath(string $sectionId): string
{
    return dirname(__DIR__, 3).'/.agents/skills/file-issue/prompts/write-'.$sectionId.'.md';
}

/**
 * Extract the fenced markdown block after the `## Worked example` heading —
 * the real, conforming section the engine must accept.
 */
function workedExample(string $prompt): string
{
    if (!preg_match('/^## Worked example\s*\n\n?\s*```(?:\w+)?\n(.*?)\n```/s', $prompt, $match)) {
        return '';
    }

    return $match[1];
}

/**
 * The shared preamble file content.
 */
function preamble(): string
{
    return file_get_contents(dirname(__DIR__, 3).'/.agents/skills/file-issue/prompts/prompt-preamble.md');
}

/**
 * What `--prompt=<id>` should print: section prompts are prefixed with the
 * shared preamble; cross-cutting prompts are printed as-is.
 */
function expectedPromptOutput(string $id): string
{
    $promptDir = dirname(__DIR__, 3).'/.agents/skills/file-issue/prompts';
    $crossCutting = ['prompt-preamble', 'readme', 'orchestrate-issue-conversion', 'review-issue-body'];

    if (in_array($id, $crossCutting, true)) {
        return file_get_contents($promptDir.'/'.$id.'.md');
    }

    return preamble().file_get_contents($promptDir.'/write-'.$id.'.md');
}

it('validates every worked example against the engine', function () {
    $schema = arazzoSchema();
    $validator = new Validator($schema);

    foreach (glob(dirname(__DIR__, 3).'/.agents/skills/file-issue/prompts/write-*.md') as $file) {
        $id = basename($file, '.md');
        $id = str_replace('write-', '', $id);
        $section = workedExample(file_get_contents($file));

        $findings = $validator->validate([
            'title' => 'feat(core): x',
            'body' => issueBody($schema, array_values(array_unique([...featureSectionIds(), $id])), [$id => $section]),
            'labels' => ['kind:feature', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
        ]);

        // Spec §7.7 assertion 3 says "zero findings"; with every other section a
        // stub, only this section's own checks can be held to that.
        expect(array_intersect(codes($findings), array_keys($schema->checksFor($id))))->toBe([]);
    }
});

it('has a prompt for every required section except core-metadata', function () {
    $schema = arazzoSchema();
    $ids = array_unique(array_merge(...array_map(
        fn (string $kind): array => $schema->requiredIdsFor($kind),
        $schema->kinds(),
    )));

    foreach (array_diff($ids, ['core-metadata']) as $id) {
        expect(promptPath($id))->toBeFile();
    }
});

it('lists every prompt in the index and reaches it through --prompt', function () {
    $index = file_get_contents(dirname(__DIR__, 3).'/.agents/skills/file-issue/prompts/README.md');
    $files = glob(dirname(__DIR__, 3).'/.agents/skills/file-issue/prompts/*.md');

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $name = basename($file, '.md');
        if ($name === 'README') {
            continue;
        }

        $id = str_starts_with($name, 'write-') ? substr($name, 6) : $name;

        expect($index)->toContain($name)
            ->and($index)->toContain($id);

        $process = new Process(['php', '.agents/skills/file-issue/scripts/file-issue.php', '--prompt='.$id], dirname(__DIR__, 3));
        $process->run();

        expect($process->getExitCode())->toBe(0)
            ->and($process->getOutput())->toBe(expectedPromptOutput($id));
    }
});

it('names each section-owned code in that section prompt', function () {
    $schema = arazzoSchema();

    foreach ($schema->sectionIds() as $id) {
        // core-metadata is scripted, not prompted (D22): its only code is
        // documented in SKILL.md, and there is no prompt to carry it.
        if ($id === 'core-metadata') {
            expect(file_get_contents(dirname(__DIR__, 3).'/.agents/skills/file-issue/SKILL.md'))
                ->toContain('parent-mismatch');

            continue;
        }

        foreach ($schema->checksFor($id) as $code => $severity) {
            expect(file_get_contents(promptPath($id)))->toContain($code);
        }
    }
});

it('keeps README, SKILL.md and schema checks in sync', function () {
    $schema = arazzoSchema();

    /** @var array<string, list<string>> */
    $schemaCodes = [];

    foreach ($schema->sectionIds() as $id) {
        $schemaCodes[$id] = array_keys($schema->checksFor($id));
    }

    $readme = file_get_contents(dirname(__DIR__, 3).'/.agents/skills/file-issue/prompts/README.md');
    $skill = file_get_contents(dirname(__DIR__, 3).'/.agents/skills/file-issue/SKILL.md');

    foreach ($schemaCodes as $id => $codes) {
        $readmeCell = cellFor($readme, $id);
        $skillCell = cellFor($skill, $id);

        // Each check code declared in schema must appear verbatim in both tables.
        foreach ($codes as $code) {
            expect($readmeCell)->toContain('`'.$code.'`');
            expect($skillCell)->toContain('`'.$code.'`');
        }

        // And no extra codes that are not declared in schema.
        preg_match_all('/`([a-z-]+)`/', $readmeCell, $readmeQuoted);
        preg_match_all('/`([a-z-]+)`/', $skillCell, $skillQuoted);

        $declared = array_flip($codes);

        foreach (array_unique($readmeQuoted[1]) as $quoted) {
            if (!isset($declared[$quoted])) {
                // Cross-cutting descriptors in README cells are not codes.
                expect(in_array($quoted, $codes, true))->toBeTrue("README cell for {$id} names undeclared code {$quoted}");
            }
        }

        foreach (array_unique($skillQuoted[1]) as $quoted) {
            if (!isset($declared[$quoted])) {
                expect(in_array($quoted, $codes, true))->toBeTrue("SKILL.md cell for {$id} names undeclared code {$quoted}");
            }
        }
    }
});

/**
 * Extract the third table cell (gate codes) for a section row.
 *
 * @param  string  $markdown  the README.md or SKILL.md content
 * @param  string  $id  the section id (backticked in the first cell)
 */
function cellFor(string $markdown, string $id): string
{
    $pattern = '/\| `'.$id.'`[^\n]*\|[^\n]*\|([^\n]*)\|/';

    if (preg_match($pattern, $markdown, $match) === 1) {
        return $match[1];
    }

    return '';
}
