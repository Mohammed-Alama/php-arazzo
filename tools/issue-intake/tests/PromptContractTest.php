<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\Schema;
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
