# Arazzo Workflow Engine for PHP & Laravel

[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE.md)

> [!WARNING]
> **Work in Progress:** This project is currently under active development and is not yet ready for production use.

This monorepo hosts the complete ecosystem for executing **Arazzo 1.0.0/1.1.0** specifications natively in PHP and Laravel. 

The [Arazzo Specification](https://github.com/OAI/Arazzo-Specification) is an extension of OpenAPI that describes sequences of API calls (workflows). While OpenAPI describes *what* endpoints exist, Arazzo describes *how* to chain those endpoints together to complete complex, multi-step tasks. This ecosystem provides a powerful execution engine to run those workflows dynamically within your PHP applications.

---

## 📦 Packages

This repository is organized as a monorepo containing the following packages:

| Package | Description | Version |
|---------|-------------|---------|
| **[alama/arazzo-core](packages/core)** | Framework-agnostic PHP engine. Includes the Arazzo parser, validator, step executor, dependency graph resolution, and JSONPath expression resolver. | `^1.0@alpha` |
| **[alama/laravel-arazzo](packages/laravel)** | Laravel bridge. Deeply integrates the core engine into your Laravel applications with service providers, async queue execution, cache locking, and Eloquent model adapters. | `^2.0@alpha` |

---

## 🚀 Features

- **Full Arazzo Spec Support**: Parses and executes workflows defined in Arazzo 1.0.0 and 1.1.0 formats (YAML/JSON).
- **Expression Resolution**: Dynamically resolves `$inputs`, `$steps`, `$response.body`, and custom variables using powerful JSONPath expressions.
- **Dependency Management**: Automatically resolves and guarantees step execution dependencies (`dependsOn`).
- **Success Criteria Evaluation**: Enforces criteria validation natively before proceeding to the next steps.
- **Framework Agnostic**: The core works natively with any PSR-compliant PHP application (PSR-7, PSR-18, PSR-3, PSR-16, PSR-14, PSR-11).
- **Laravel Native**: Zero-friction setup for Laravel developers. Runs workflows natively on Laravel Queues.

---

## 💻 Installation

Depending on your framework, you can install the core or the Laravel integration package.

### For Laravel Projects
```bash
composer require alama/laravel-arazzo
```
The Service Provider is auto-discovered. You can publish the configuration using:
```bash
php artisan vendor:publish --tag="arazzo-config"
```

### For Plain PHP Projects
```bash
composer require alama/arazzo-core
```

---

## 🛠 Usage Overview

*This is a high-level overview. Please consult the README in each individual package directory for detailed documentation.*

### Laravel Example

With the Laravel package, executing an Arazzo workflow can be natively dispatched to your queues:

```php
use Alama\Arazzo\Laravel\Facades\Arazzo;

// Execute an Arazzo workflow definition
Arazzo::workflow('path/to/workflow.arazzo.yaml')
    ->execute('complete-ride-booking', [
        'departure_polygon_id' => 123,
        'destination_polygon_id' => 456,
        'customer_id' => 789,
    ]);
```

### Core Engine Example (Framework-Agnostic)

Using the core executor manually. You can test this exact setup natively by running `php scripts/manual_test.php` in the root of this repository.

```php
use Alama\Arazzo\Dto\RawDocument;
use Alama\Arazzo\Dto\Enum\Format;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Execution\WorkflowExecutor;
use Alama\Arazzo\Execution\StepExecutor;
use Alama\Arazzo\Execution\ArazzoExpressionResolver;
use Alama\Arazzo\Execution\ExpressionEvaluator;
use Alama\Arazzo\Resolution\DefaultSourceResolver;
use Alama\Arazzo\Resolution\Fetchers\HttpFetcher;
use Alama\Arazzo\Resolution\Fetchers\LocalFetcher;
use Alama\Arazzo\Resolution\Parsers\ArazzoSourceParser;
use Alama\Arazzo\Resolution\Parsers\OpenApiSourceParser;
use Symfony\Component\Yaml\Yaml;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

$path = 'LoginAndRetrievePets.arazzo.yaml';
$raw = new RawDocument(Yaml::parseFile($path), $path, Format::Yaml);

$parser = new Parser();
$document = $parser->parse($raw);

// Find the workflow by ID
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

$result = $executor->execute($workflow, $document, [
    'username' => 'testuser',
    'password' => 'password123',
]);
        
echo "Workflow execution finished with status: {$result->status}\n";

foreach ($result->stepResults as $stepId => $stepResult) {
    $status = $stepResult->success ? 'Success' : 'Failed';
    echo " - Step {$stepId}: {$status}\n";
    if (!empty($stepResult->outputs)) {
        print_r($stepResult->outputs);
    }
}
```

---

## 🧪 Testing

We use [Pest PHP](https://pestphp.com/) for testing and [PHPStan](https://phpstan.org/) for static analysis.

Run all tests across the monorepo:
```bash
composer test
```

Run static analysis:
```bash
composer analyse
```

Format code:
```bash
composer format
```

For more advanced testing features via Make:
```bash
make verify
```

---

## 🤝 Contributing

We welcome contributions! Please see the issue tracker to report bugs, suggest features, or submit Pull Requests. Be sure to run `make verify` before submitting code to ensure it passes all style, static analysis, and testing requirements.

## 📄 License

The MIT License (MIT). Please see the [License File](LICENSE.md) for more information.
