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
            ->and($process->getOutput())->toBe(file_get_contents($file));
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
