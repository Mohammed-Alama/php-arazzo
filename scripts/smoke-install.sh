#!/usr/bin/env bash
#
# Clean-installation smoke test: installs both monorepo packages into a
# throwaway Composer project using path repositories, verifies autoload,
# and bootstraps a minimal engine wiring.
#
# Usage: bash scripts/smoke-install.sh
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d /tmp/arazzo-smoke.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT

echo "==> smoke project: $TMP"
cd "$TMP"

# Point path repositories straight at the monorepo package directories so
# PSR-4 autoload roots resolve to their real src/ trees.
cat > composer.json <<JSON
{
    "require": {
        "alama/arazzo-core": "@dev",
        "alama/laravel-arazzo": "@dev"
    },
    "repositories": [
        {"type": "path", "url": "$REPO_ROOT/packages/core", "options": {"symlink": true}},
        {"type": "path", "url": "$REPO_ROOT/packages/laravel", "options": {"symlink": true}}
    ]
}
JSON

composer install --quiet --no-interaction --no-progress

php -r '
require $argv[1]."/vendor/autoload.php";

use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Spec\Enum\Format;
use Alama\Arazzo\Spec\RawDocument;
use Alama\Arazzo\Resolver\SourceRegistry;
use Alama\Arazzo\Runner\Evaluation\ArazzoCriteriaEvaluator;
use Alama\Arazzo\Runner\Evaluation\ArazzoExpressionResolver;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Runner\Execution\ArazzoOutputExtractor;
use Alama\Arazzo\Runner\Execution\ArazzoSchemaValidator;
use Alama\Arazzo\Runner\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Runner\Execution\OpenApiDocumentLoader;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use Alama\Arazzo\Runner\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use GuzzleHttp\Psr7\HttpFactory;

$doc = (new Parser())->parse(new RawDocument([
    "arazzo" => "1.0.1",
    "info" => ["title" => "smoke", "version" => "1.0.0"],
    "sourceDescriptions" => [["name" => "api", "url" => "https://smoke.invalid/api.json", "type" => "openapi"]],
    "workflows" => [["workflowId" => "wf", "steps" => [["stepId" => "s1"]]]],
], "memory://smoke.json", Format::Json));

$registry = new SourceRegistry(new \Alama\Arazzo\Resolver\DefaultSourceResolver([]));
$ops = new OpenApiOperationResolver(new OpenApiDocumentLoader($registry), new OpenApiVersionDetector(), new OpenApi30Normalizer(), new OpenApi31Normalizer());
$evaluator = new ExpressionEvaluator();
$resolver = new ArazzoExpressionResolver($evaluator, new ArazzoOutputExtractor($ops, $evaluator), new ArazzoCriteriaEvaluator($evaluator), new ArazzoSchemaValidator($ops));

$executor = new WorkflowExecutor(
    new StepExecutor(new DefaultOpenApiExecutor(new class implements Psr\Http\Client\ClientInterface {
        public function sendRequest(Psr\Http\Message\RequestInterface $request): Psr\Http\Message\ResponseInterface
        {
            return new \GuzzleHttp\Psr7\Response(200, [], "{}");
        }
    }, new HttpFactory()), $resolver, $ops),
    workflowEngine: new WorkflowEngine($resolver),
);

echo get_class($executor), "\n";
' "$TMP"

echo "==> smoke OK"
