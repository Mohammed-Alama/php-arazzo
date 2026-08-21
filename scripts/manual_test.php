<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

error_reporting(E_ALL & ~E_DEPRECATED);

use Alama\Arazzo\Loader\Loader;
use Alama\Arazzo\Loader\NativeJsonDecoder;
use Alama\Arazzo\Loader\SymfonyYamlDecoder;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\Fetchers\HttpFetcher;
use Alama\Arazzo\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Resolver\Parsers\ArazzoSourceParser;
use Alama\Arazzo\Resolver\Parsers\AsyncApiSourceParser;
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

$fixturesDir = __DIR__ . '/../packages/core/tests/fixtures/';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixturesDir));
$yamlFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile() && (str_ends_with($file->getFilename(), '.yaml') || str_ends_with($file->getFilename(), '.json'))) {
        $yamlFiles[] = $file->getPathname();
    }
}

// Predefined inputs for workflows that require them
$workflowInputs = [
    'apply-coupon' => [
        'my_product_category' => 'electronics',
        'store_id' => 'store.example.com',
    ],
    'buy-available-product' => [
        'store_id' => 'store.example.com',
    ],
    'place-order' => [
        'product_id' => 1,
        'quantity' => 1,
        'coupon_code' => 'DISCOUNT20',
    ],
    'ApplyForLoanAtCheckout' => [
        'customer' => [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'dateOfBirth' => '1990-01-01',
            'postalCode' => '12345',
        ],
        'amount' => [
            'currency' => 'USD',
            'value' => 100.0,
        ],
        'orderReference' => 'ORD-12345',
    ],
];

echo "Testing all Arazzo workflows against local dummy app (http://localhost:8002)...\n";
echo 'Found ' . count($yamlFiles) . " fixture files.\n\n";

// Wire up the core engine dependencies manually (Framework-Agnostic)
$client = new Client();
$httpFactory = new HttpFactory();
$parser = new Parser();

$fetchers = [
    'http' => new HttpFetcher($client, $httpFactory),
    'https' => new HttpFetcher($client, $httpFactory),
    'file' => new LocalFetcher(),
];

$parsers = [
    'arazzo' => new ArazzoSourceParser($parser),
    'openapi' => new OpenApiSourceParser(),
    'asyncapi' => new AsyncApiSourceParser(),
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

$loader = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());

foreach ($yamlFiles as $path) {
    echo "=================================================\n";
    $relPath = str_replace(__DIR__ . '/../packages/core/tests/fixtures/', '', $path);
    echo "Loading fixture: {$relPath}\n";

    try {
        $raw = $loader->load($path);
        $document = $parser->parse($raw);

        foreach ($document->workflows as $workflow) {
            echo "-------------------------------------------------\n";
            echo "Executing workflow '{$workflow->workflowId}'...\n";

            $inputs = $workflowInputs[$workflow->workflowId] ?? [];
            $result = $executor->execute($workflow, $document, $inputs);

            echo "Workflow status: {$result->status}\n";
            foreach ($result->stepResults as $stepId => $stepResult) {
                $status = $stepResult->success ? 'Success' : 'Failed';
                echo " - Step '{$stepId}': {$status}\n";
            }
        }
    } catch (Throwable $e) {
        echo "Failed processing fixture!\n";
        echo $e->getMessage() . "\n";
    }
    echo "\n";
}
