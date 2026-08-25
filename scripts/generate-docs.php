<?php

/**
 * Generates architecture documentation (mermaid diagrams) from the live source tree
 * into docs/generated/. Deterministic: same tree => byte-identical output.
 *
 * Run manually:   composer docs   (or: php scripts/generate-docs.php)
 * Run by:         .githooks/pre-commit (regenerates and stages changes)
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$outDir = $root . '/docs/generated';

require __DIR__ . '/generate-docs/Scanner.php';
require __DIR__ . '/generate-docs/NamespaceGraphDoc.php';
require __DIR__ . '/generate-docs/DocumentModelDoc.php';
require __DIR__ . '/generate-docs/ContractsDoc.php';
require __DIR__ . '/generate-docs/EventsDoc.php';
require __DIR__ . '/generate-docs/ValidatorRulesDoc.php';
require __DIR__ . '/generate-docs/ExceptionTreeDoc.php';
require __DIR__ . '/generate-docs/ExpressionAstDoc.php';
require __DIR__ . '/generate-docs/DatabaseSchemaDoc.php';
require __DIR__ . '/generate-docs/PipelineFlowDoc.php';
require __DIR__ . '/generate-docs/PublicApiDoc.php';
require __DIR__ . '/generate-docs/CouplingMetricsDoc.php';
require __DIR__ . '/generate-docs/QualityGatesDoc.php';
require __DIR__ . '/generate-docs/FailureModesDoc.php';
require __DIR__ . '/generate-docs/SecuritySurfaceDoc.php';
require __DIR__ . '/generate-docs/StateMachineDoc.php';
require __DIR__ . '/generate-docs/LayeringDoc.php';
require __DIR__ . '/generate-docs/CoverageRiskDoc.php';
require __DIR__ . '/generate-docs/ChurnHotspotsDoc.php';
require __DIR__ . '/generate-docs/DependencyFlowDoc.php';
require __DIR__ . '/generate-docs/TestCompositionDoc.php';

use ArazzoDocs\ChurnHotspotsDoc;
use ArazzoDocs\ContractsDoc;
use ArazzoDocs\CouplingMetricsDoc;
use ArazzoDocs\CoverageRiskDoc;
use ArazzoDocs\DatabaseSchemaDoc;
use ArazzoDocs\DependencyFlowDoc;
use ArazzoDocs\DocumentModelDoc;
use ArazzoDocs\EventsDoc;
use ArazzoDocs\ExceptionTreeDoc;
use ArazzoDocs\ExpressionAstDoc;
use ArazzoDocs\FailureModesDoc;
use ArazzoDocs\LayeringDoc;
use ArazzoDocs\NamespaceGraphDoc;
use ArazzoDocs\PipelineFlowDoc;
use ArazzoDocs\PublicApiDoc;
use ArazzoDocs\QualityGatesDoc;
use ArazzoDocs\Scanner;
use ArazzoDocs\SecuritySurfaceDoc;
use ArazzoDocs\StateMachineDoc;
use ArazzoDocs\TestCompositionDoc;
use ArazzoDocs\ValidatorRulesDoc;

if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$core = Scanner::scan($root . '/packages/core/src', 'Alama\\Arazzo\\');
$laravel = Scanner::scan($root . '/packages/laravel/src', 'Alama\\Arazzo\\Laravel\\');

$generated = [
    'namespace-graph.md' => NamespaceGraphDoc\render($core, $laravel),
    'document-model.md' => DocumentModelDoc\render($core),
    'contracts.md' => ContractsDoc\render($core, $laravel),
    'events.md' => EventsDoc\render($core, $laravel),
    'validator-rules.md' => ValidatorRulesDoc\render($core),
    'exceptions.md' => ExceptionTreeDoc\render($core, $laravel),
    'expression-ast.md' => ExpressionAstDoc\render($core),
    'database-schema.md' => DatabaseSchemaDoc\render($root . '/packages/laravel/database/migrations'),
    'pipeline-flow.md' => PipelineFlowDoc\render($core, $laravel),
    'public-api.md' => PublicApiDoc\render($core, $laravel),
    'coupling-metrics.md' => CouplingMetricsDoc\render($core, $laravel),
    'quality-gates.md' => QualityGatesDoc\render($root . '/storage/quality-gates.json'),
    'failure-modes.md' => FailureModesDoc\render($core, $laravel),
    'security-surface.md' => SecuritySurfaceDoc\render($core, $laravel),
    'state-machine.md' => StateMachineDoc\render($core, $laravel),
    'layering.md' => LayeringDoc\render($core, $laravel),
    'coverage-risk.md' => CoverageRiskDoc\render($core, $laravel, $root),
    'churn-hotspots.md' => ChurnHotspotsDoc\render($core, $laravel, $root),
    'dependency-flow.md' => DependencyFlowDoc\render($core, $laravel),
    'test-composition.md' => TestCompositionDoc\render(root: $root),
];

foreach ($generated as $file => $content) {
    file_put_contents($outDir . '/' . $file, $content);
}

echo 'Generated ' . count($generated) . ' docs in docs/generated/: ' . implode(', ', array_keys($generated)) . "\n";
