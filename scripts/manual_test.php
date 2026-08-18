<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

error_reporting(E_ALL & ~E_DEPRECATED);

use Alama\Arazzo\Dto\Enum\Format;
use Alama\Arazzo\Dto\RawDocument;
use Alama\Arazzo\Execution\ArazzoExpressionResolver;
use Alama\Arazzo\Execution\ExpressionEvaluator;
use Alama\Arazzo\Execution\StepExecutor;
use Alama\Arazzo\Execution\WorkflowExecutor;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Resolution\DefaultSourceResolver;
use Alama\Arazzo\Resolution\Fetchers\HttpFetcher;
use Alama\Arazzo\Resolution\Fetchers\LocalFetcher;
use Alama\Arazzo\Resolution\Parsers\ArazzoSourceParser;
use Alama\Arazzo\Resolution\Parsers\OpenApiSourceParser;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Symfony\Component\Yaml\Yaml;

$path = __DIR__ . '/../LoginAndRetrievePets.arazzo.yaml';
if (!file_exists($path)) {
    echo "Downloading LoginAndRetrievePets.arazzo.yaml...\n";
    file_put_contents($path, file_get_contents('https://raw.githubusercontent.com/OAI/Arazzo-Specification/main/examples/1.0.0/LoginAndRetrievePets.arazzo.yaml'));
}

echo "Testing Arazzo workflow...\n";

$raw = new RawDocument(Yaml::parseFile($path), $path, Format::Yaml);
$parser = new Parser();
$document = $parser->parse($raw);

$workflow = null;
foreach ($document->workflows as $w) {
    if ($w->workflowId === 'loginUserRetrievePet') {
        $workflow = $w;
        break;
    }
}

// Wire up the core engine dependencies manually (Framework-Agnostic)
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
$expressionResolver = new ArazzoExpressionResolver($sourceResolver, $httpFactory, $evaluator);
$stepExecutor = new StepExecutor($client, $expressionResolver);
$executor = new WorkflowExecutor($stepExecutor);

try {
    echo "Executing workflow '{$workflow->workflowId}'...\n";

    $result = $executor->execute($workflow, $document, [
        'username' => 'testuser',
        'password' => 'password123',
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
} catch (Throwable $e) {
    echo "Workflow Execution Failed!\n";
    echo $e->getMessage() . "\n";
}
