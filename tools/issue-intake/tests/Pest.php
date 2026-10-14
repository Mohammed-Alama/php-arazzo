<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\Schema;

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
        $body .= '### '.$schema->section($id)['heading']."\n\n".($content[$id] ?? "filled {$id}")."\n\n";
    }

    return $body;
}

/** @return list<string> */
function bugSectionIds(): array
{
    return [
        'core-metadata', 'problem-statement', 'dependency-matrix', 'artifacts', 'runtime-state',
        'acceptance-criteria', 'source-grounding', 'impact-profile', 'epistemic-matrix',
    ];
}

/** @return list<string> */
function enhancementSectionIds(): array
{
    return [
        'core-metadata', 'problem-statement', 'feature-scope', 'acceptance-criteria',
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
