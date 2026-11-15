<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\Schema;
use Symfony\Component\Process\Process;

require_once __DIR__.'/../../../vendor/autoload.php';

function arazzoSchema(): Schema
{
    return Schema::fromFile(dirname(__DIR__, 3).'/config/issues/schema.yaml');
}

/**
 * Render a body holding the given sections, in order, each with content.
 *
 * @param  list<string>  $ids
 * @param  array<string, string>  $content
 */
function issueBody(Schema $schema, array $ids, array $content = []): string
{
    $body = '';

    foreach ($ids as $id) {
        $body .= $schema->section($id)['heading']."\n\n".($content[$id] ?? "filled {$id}")."\n\n";
    }

    return $body;
}

/** @return list<string> */
function bugfixSectionIds(): array
{
    return [
        'core-metadata', 'problem-statement', 'dependency-matrix', 'artifacts', 'runtime-state',
        'acceptance-criteria', 'source-grounding', 'impact-profile', 'epistemic-matrix',
    ];
}

/** @return list<string> */
function featureSectionIds(): array
{
    return [
        'core-metadata', 'problem-statement', 'feature-scope', 'acceptance-criteria',
        'source-grounding', 'impact-profile', 'epistemic-matrix',
    ];
}

/** @return list<string> */
function releaseSectionIds(): array
{
    return [
        'core-metadata', 'problem-statement', 'affected-surface', 'acceptance-criteria',
        'source-grounding', 'impact-profile', 'epistemic-matrix',
    ];
}

/** @return list<string> */
function choreSectionIds(): array
{
    return [
        'core-metadata', 'problem-statement', 'affected-surface', 'acceptance-criteria',
        'source-grounding', 'impact-profile',
    ];
}

/** @return array<string, string> section id => content that passes every v2 check */
function conformingSections(Schema $schema, string $kind, ?int $parent = null): array
{
    // HEAD sha and today's date keep the permalink and Verified: line valid.
    $sha = trim((new Process(['git', 'rev-parse', 'HEAD']))->mustRun()->getOutput());

    $permalink = "- the validator that enforces every structural code.\n"
        ."https://github.com/Mohammed-Alama/php-arazzo/blob/{$sha}/tools/issue-intake/src/Validator.php#L1-L10\n"
        .'Verified: '.date('Y-m-d');

    $table = "| Quality | Covered by | Note |\n| --- | --- | --- |\n"
        .implode('', array_map(
            static fn (string $q): string => "| {$q} | S1 | covered |\n",
            ['Efficient', 'Flexible', 'Maintainable', 'Operable', 'Reliable', 'Safe', 'Secure', 'Suitable', 'Usable'],
        ));

    return [
        'core-metadata' => 'Package: core'.($parent === null ? '' : " · Parent: #{$parent}"),
        'problem-statement' => 'R1. one observable requirement.',
        'feature-scope' => "Goals: x\nNon-goals: y",
        'dependency-matrix' => 'package/core · PHP 8.4',
        'affected-surface' => "Risk: low\nRollback: revert the commit",
        'artifacts' => "```json\n{\"x\":1}\n```",
        'runtime-state' => 'phase: execute · step 1 · action goto',
        'acceptance-criteria' => "S1 (R1, Reliable): one scenario\n  Given a step\n  When it resolves\n"
            ."  Then it throws UnresolvedWorkflowException\n  Test: WorkflowEngineTest::it_rejects_unknown\n\n"
            ."C1. one invariant\n  Check: the engine throws\n\n".$table,
        'source-grounding' => $permalink,
        'impact-profile' => 'API: none · BC break: no',
        'epistemic-matrix' => "| Claim | Tier | Evidence | Verification |\n"
            ."| --- | --- | --- | --- |\n| x | T1 | file | read |",
    ];
}

function conformingBody(Schema $schema, string $kind, ?int $parent = null): string
{
    return issueBody($schema, $schema->requiredIdsFor($kind), conformingSections($schema, $kind, $parent));
}

/** @param  list<\Alama\Tools\IssueIntake\Finding>  $findings  @return list<string> */
function codes(array $findings): array
{
    return array_map(static fn ($finding): string => $finding->code, $findings);
}
