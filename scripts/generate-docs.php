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
$outDir = $root.'/docs/generated';

require __DIR__.'/generate-docs/Scanner.php';
require __DIR__.'/generate-docs/NamespaceGraphDoc.php';
require __DIR__.'/generate-docs/DocumentModelDoc.php';
require __DIR__.'/generate-docs/ContractsDoc.php';
require __DIR__.'/generate-docs/EventsDoc.php';
require __DIR__.'/generate-docs/ValidatorRulesDoc.php';
require __DIR__.'/generate-docs/ExceptionTreeDoc.php';
require __DIR__.'/generate-docs/ExpressionAstDoc.php';
require __DIR__.'/generate-docs/DatabaseSchemaDoc.php';
require __DIR__.'/generate-docs/PipelineFlowDoc.php';
require __DIR__.'/generate-docs/PublicApiDoc.php';
require __DIR__.'/generate-docs/CouplingMetricsDoc.php';
require __DIR__.'/generate-docs/QualityGatesDoc.php';
require __DIR__.'/generate-docs/FailureModesDoc.php';
require __DIR__.'/generate-docs/SecuritySurfaceDoc.php';
require __DIR__.'/generate-docs/StateMachineDoc.php';
require __DIR__.'/generate-docs/LayeringDoc.php';
require __DIR__.'/generate-docs/CoverageRiskDoc.php';
require __DIR__.'/generate-docs/ChurnHotspotsDoc.php';
require __DIR__.'/generate-docs/DependencyFlowDoc.php';
require __DIR__.'/generate-docs/TestCompositionDoc.php';
require __DIR__.'/generate-docs/CliReferenceDoc.php';
require __DIR__.'/generate-docs/BcDiffDoc.php';
require __DIR__.'/generate-docs/ObservabilityDoc.php';
require __DIR__.'/generate-docs/IntegrationContextDoc.php';
require __DIR__.'/generate-docs/ExtensionPointsDoc.php';
require __DIR__.'/generate-docs/TrustBoundaryFlowDoc.php';
require __DIR__.'/generate-docs/GateTrendDoc.php';
require __DIR__.'/generate-docs/ModularizationProgressDoc.php';
require __DIR__.'/generate-docs/FitnessFunctionsDoc.php';
require __DIR__.'/generate-docs/TestEconomicsDoc.php';
require __DIR__.'/generate-docs/SolidMetricsDoc.php';
require __DIR__.'/generate-docs/BoundariesAuditDoc.php';
require __DIR__.'/generate-docs/UbiquitousLanguageAuditDoc.php';
require __DIR__.'/generate-docs/SubdomainMapDoc.php';
require __DIR__.'/generate-docs/AggregateMapDoc.php';

use ArazzoDocs\AggregateMapDoc;
use ArazzoDocs\BcDiffDoc;
use ArazzoDocs\BoundariesAuditDoc;
use ArazzoDocs\ChurnHotspotsDoc;
use ArazzoDocs\CliReferenceDoc;
use ArazzoDocs\ContractsDoc;
use ArazzoDocs\CouplingMetricsDoc;
use ArazzoDocs\CoverageRiskDoc;
use ArazzoDocs\DatabaseSchemaDoc;
use ArazzoDocs\DependencyFlowDoc;
use ArazzoDocs\DocumentModelDoc;
use ArazzoDocs\EventsDoc;
use ArazzoDocs\ExceptionTreeDoc;
use ArazzoDocs\ExpressionAstDoc;
use ArazzoDocs\ExtensionPointsDoc;
use ArazzoDocs\FailureModesDoc;
use ArazzoDocs\FitnessFunctionsDoc;
use ArazzoDocs\GateTrendDoc;
use ArazzoDocs\IntegrationContextDoc;
use ArazzoDocs\LayeringDoc;
use ArazzoDocs\ModularizationProgressDoc;
use ArazzoDocs\NamespaceGraphDoc;
use ArazzoDocs\ObservabilityDoc;
use ArazzoDocs\PipelineFlowDoc;
use ArazzoDocs\PublicApiDoc;
use ArazzoDocs\QualityGatesDoc;
use ArazzoDocs\Scanner;
use ArazzoDocs\SecuritySurfaceDoc;
use ArazzoDocs\SolidMetricsDoc;
use ArazzoDocs\StateMachineDoc;
use ArazzoDocs\SubdomainMapDoc;
use ArazzoDocs\TestCompositionDoc;
use ArazzoDocs\TestEconomicsDoc;
use ArazzoDocs\TrustBoundaryFlowDoc;
use ArazzoDocs\UbiquitousLanguageAuditDoc;
use ArazzoDocs\ValidatorRulesDoc;

if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$scans = [];
foreach (\ArazzoDocs\CORE_SRC_PACKAGES as $package) {
    $scans[$package] = Scanner::scan($root.'/packages/'.$package.'/src', 'Alama\\Arazzo\\', $package);
}
$scans['laravel'] = Scanner::scan($root.'/packages/laravel/src', 'Alama\\Arazzo\\Laravel\\', 'laravel');

// All renderers consume per-package $scans (package slug => module => files);
// the legacy bare-module $core/$laravel merge is gone (was the source of
// cross-package module collisions).
$layerOrder = \ArazzoDocs\packageLayerOrder($root);

$generated = [
    'namespace-graph.md' => NamespaceGraphDoc\render($scans),
    'document-model.md' => DocumentModelDoc\render($scans),
    'contracts.md' => ContractsDoc\render($scans),
    'events.md' => EventsDoc\render($scans),
    'validator-rules.md' => ValidatorRulesDoc\render($scans),
    'exceptions.md' => ExceptionTreeDoc\render($scans),
    'expression-ast.md' => ExpressionAstDoc\render($scans),
    'database-schema.md' => DatabaseSchemaDoc\render($root.'/packages/laravel/database/migrations'),
    'pipeline-flow.md' => PipelineFlowDoc\render($scans),
    'public-api.md' => PublicApiDoc\render($scans),
    'coupling-metrics.md' => CouplingMetricsDoc\render($scans),
    'quality-gates.md' => QualityGatesDoc\render($root.'/storage/quality-gates.json'),
    'failure-modes.md' => FailureModesDoc\render($scans),
    'security-surface.md' => SecuritySurfaceDoc\render($scans),
    'state-machine.md' => StateMachineDoc\render($scans),
    'layering.md' => LayeringDoc\render($scans, $layerOrder),
    'coverage-risk.md' => CoverageRiskDoc\render($scans, $root),
    'churn-hotspots.md' => ChurnHotspotsDoc\render($scans, $root),
    'dependency-flow.md' => DependencyFlowDoc\render($scans),
    'test-composition.md' => TestCompositionDoc\render(root: $root),
    'cli-reference.md' => CliReferenceDoc\render($root),
    'bc-diff.md' => BcDiffDoc\render($root),
    'observability.md' => ObservabilityDoc\render($scans),
    'integration-context.md' => IntegrationContextDoc\render($scans),
    'extension-points.md' => ExtensionPointsDoc\render($scans),
    'trust-boundary-flow.md' => TrustBoundaryFlowDoc\render($scans),
    'gate-trend.md' => GateTrendDoc\render(historyPath: $root.'/storage/quality-history.jsonl'),
    'modularization-progress.md' => ModularizationProgressDoc\render($root),
    'fitness-functions.md' => FitnessFunctionsDoc\render($root),
    'test-economics.md' => TestEconomicsDoc\render($scans, $root),
    'solid-metrics.md' => SolidMetricsDoc\render($scans),
    'boundaries-audit.md' => BoundariesAuditDoc\render($scans),
    'ubiquitous-language-audit.md' => UbiquitousLanguageAuditDoc\render($scans),
    'subdomain-map.md' => SubdomainMapDoc\render($scans),
    'aggregate-map.md' => AggregateMapDoc\render($scans),
];

foreach ($generated as $file => $content) {
    file_put_contents($outDir.'/'.$file, $content);
}

echo 'Generated '.count($generated).' docs in docs/generated/: '.implode(', ', array_keys($generated))."\n";
