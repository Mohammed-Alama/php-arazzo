<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

error_reporting(E_ALL & ~E_DEPRECATED);

use Alama\Arazzo\Dto\Enum\Format;
use Alama\Arazzo\Dto\RawDocument;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\Fetchers\HttpFetcher;
use Alama\Arazzo\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Resolver\Parsers\ArazzoSourceParser;
use Alama\Arazzo\Resolver\Parsers\OpenApiSourceParser;
use Alama\Arazzo\Runner\ArazzoCriteriaEvaluator;
use Alama\Arazzo\Runner\ArazzoExpressionResolver;
use Alama\Arazzo\Runner\ArazzoOutputExtractor;
use Alama\Arazzo\Runner\ArazzoRequestCompiler;
use Alama\Arazzo\Runner\ArazzoSchemaValidator;
use Alama\Arazzo\Runner\ExpressionEvaluator;
use Alama\Arazzo\Runner\IdempotencyKeyInjector;
use Alama\Arazzo\Runner\StepExecutor;
use Alama\Arazzo\Runner\WorkflowExecutor;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Symfony\Component\Yaml\Yaml;

$path = __DIR__.'/../../arazzo-dummy-app/checkout.arazzo.yaml';

echo "Testing Checkout Arazzo workflow...\n";

$raw = new RawDocument(Yaml::parseFile($path), $path, Format::Yaml);
$parser = new Parser();
$document = $parser->parse($raw);

$workflow = null;
foreach ($document->workflows as $w) {
    if ($w->workflowId === 'ecomCheckoutFlow') {
        $workflow = $w;
        break;
    }
}

$client = new Client();
$httpFactory = new HttpFactory();

$fetchers = [
    'http' => new HttpFetcher($client, $httpFactory),
    'https' => new HttpFetcher($client, $httpFactory),
    'file' => new LocalFetcher(),
];

$parsers = [
    'arazzo' => new ArazzoSourceParser($parser),
    'openapi' => new OpenApiSourceParser(),
];

$sourceResolver = new DefaultSourceResolver($fetchers, $parsers);
$evaluator = new ExpressionEvaluator();
$requestCompiler = new ArazzoRequestCompiler($sourceResolver, $httpFactory, $evaluator);
$outputExtractor = new ArazzoOutputExtractor($sourceResolver, $evaluator);
$criteriaEvaluator = new ArazzoCriteriaEvaluator($evaluator);
$schemaValidator = new ArazzoSchemaValidator($sourceResolver);
$expressionResolver = new ArazzoExpressionResolver($evaluator, $requestCompiler, $outputExtractor, $criteriaEvaluator, $schemaValidator);
$idempotencyKeyInjector = new IdempotencyKeyInjector(false, 'Idempotency-Key');
$stepExecutor = new StepExecutor($client, $expressionResolver, false, $idempotencyKeyInjector);
$executor = new WorkflowExecutor($stepExecutor);

try {
    echo "Executing workflow '{$workflow->workflowId}'...\n";

    $result = $executor->execute($workflow, $document, [
        'product_id' => 101,
        'quantity' => 2,
        'payment_method' => 'credit_card',
    ]);

    echo "Workflow execution finished with status: {$result->status}\n";

    echo "Step Results:\n";
    foreach ($result->stepResults as $stepId => $stepResult) {
        $status = $stepResult->success ? 'Success' : 'Failed';
        echo " - Step {$stepId}: {$status}\n";
        if (!empty($stepResult->outputs)) {
            print_r($stepResult->outputs);
        }
    }

    echo "Workflow Outputs:\n";
    print_r($result->outputs);
} catch (Throwable $e) {
    echo "Workflow Execution Failed!\n";
    echo $e->getMessage()."\n";
}
