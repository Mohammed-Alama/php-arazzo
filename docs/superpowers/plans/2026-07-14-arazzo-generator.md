# Arazzo AI Generator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement an AI-assisted generator that takes an OpenAPI document and a natural-language goal, and outputs a validated Arazzo workflow YAML using a self-correcting reflection loop.

**Architecture:** A Service-Oriented approach. `ArazzoGenerator` orchestrates the process. It uses `PromptBuilder` to format the OpenAPI context, `LlmDriver` to communicate with the AI model, and our core `Validator` to check the output. If invalid, the errors are fed back to the LLM.

**Tech Stack:** PHP 8.2+, Laravel HTTP Client, `cebe/php-openapi` (already installed), Pest PHP.

---

### Task 1: Core Interfaces and Configuration

**Files:**
- Create: `src/Generator/Exceptions/GenerationException.php`
- Create: `src/Generator/LlmDriver.php`
- Modify: `config/arazzo.php`

- [ ] **Step 1: Create Exception and Interface**

```php
// src/Generator/Exceptions/GenerationException.php
namespace Alama\LaravelArazzo\Generator\Exceptions;

class GenerationException extends \RuntimeException {}

// src/Generator/LlmDriver.php
namespace Alama\LaravelArazzo\Generator;

interface LlmDriver
{
    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @return string The raw response content
     */
    public function chat(array $messages): string;
}
```

- [ ] **Step 2: Update Configuration**

Add the `generator` block to `config/arazzo.php`. If the file doesn't exist yet, create it.

```php
// config/arazzo.php (add to existing array or create new)
return [
    'strict' => env('ARAZZO_STRICT', true),
    'rules' => [
        'disabled' => [],
    ],
    'output' => [
        'default_format' => 'human',
    ],
    'generator' => [
        'driver' => 'http',
        'http' => [
            'endpoint' => env('ARAZZO_LLM_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
            'api_key' => env('ARAZZO_LLM_API_KEY'),
            'model' => env('ARAZZO_LLM_MODEL', 'gpt-4o'),
        ],
        'max_retries' => 3,
        'system_prompt' => "You are an expert Arazzo 1.0.0 workflow generator. Your task is to map the provided OpenAPI endpoints to a valid Arazzo workflow YAML that achieves the user's goal. Only output valid YAML, without markdown blocks.",
    ],
];
```

- [ ] **Step 3: Commit**

```bash
rtk git add src/Generator/ config/arazzo.php
rtk git commit -m "feat: add generator core interfaces and config"
```

---

### Task 2: Implement DefaultHttpDriver

**Files:**
- Create: `src/Generator/Drivers/DefaultHttpDriver.php`
- Create: `tests/Generator/Drivers/DefaultHttpDriverTest.php`

- [ ] **Step 1: Write test for DefaultHttpDriver**

```php
// tests/Generator/Drivers/DefaultHttpDriverTest.php
use Alama\LaravelArazzo\Generator\Drivers\DefaultHttpDriver;
use Alama\LaravelArazzo\Generator\Exceptions\GenerationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

it('sends messages and extracts content', function () {
    Config::set('arazzo.generator.http.endpoint', 'https://api.test/chat');
    Config::set('arazzo.generator.http.api_key', 'test-key');
    Config::set('arazzo.generator.http.model', 'test-model');

    Http::fake([
        '*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'generated yaml']]
            ]
        ], 200)
    ]);

    $driver = new DefaultHttpDriver();
    $result = $driver->chat([['role' => 'user', 'content' => 'hello']]);

    expect($result)->toBe('generated yaml');
});

it('throws on http error', function () {
    Config::set('arazzo.generator.http.endpoint', 'https://api.test/chat');
    Http::fake(['*' => Http::response('Unauthorized', 401)]);

    $driver = new DefaultHttpDriver();
    $driver->chat([['role' => 'user', 'content' => 'hello']]);
})->throws(GenerationException::class);
```

- [ ] **Step 2: Run test to see failure**

Run: `rtk php artisan test --filter DefaultHttpDriverTest`
Expected: FAIL

- [ ] **Step 3: Implement DefaultHttpDriver**

```php
// src/Generator/Drivers/DefaultHttpDriver.php
namespace Alama\LaravelArazzo\Generator\Drivers;

use Alama\LaravelArazzo\Generator\LlmDriver;
use Alama\LaravelArazzo\Generator\Exceptions\GenerationException;
use Illuminate\Support\Facades\Http;

class DefaultHttpDriver implements LlmDriver
{
    public function chat(array $messages): string
    {
        $endpoint = config('arazzo.generator.http.endpoint');
        $apiKey = config('arazzo.generator.http.api_key');
        $model = config('arazzo.generator.http.model');

        $response = Http::withToken($apiKey)
            ->post($endpoint, [
                'model' => $model,
                'messages' => $messages,
            ]);

        if ($response->failed()) {
            throw new GenerationException("LLM API request failed: " . $response->body());
        }

        $data = $response->json();
        
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new GenerationException("Unexpected API response format.");
        }

        return $data['choices'][0]['message']['content'];
    }
}
```

- [ ] **Step 4: Run tests to verify pass**

Run: `rtk php artisan test --filter DefaultHttpDriverTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
rtk git add src/Generator/Drivers/ tests/Generator/Drivers/
rtk git commit -m "feat: implement DefaultHttpDriver for LLM communication"
```

---

### Task 3: Implement PromptBuilder

**Files:**
- Create: `src/Generator/PromptBuilder.php`
- Create: `tests/Generator/PromptBuilderTest.php`

- [ ] **Step 1: Write test for PromptBuilder**

```php
// tests/Generator/PromptBuilderTest.php
use Alama\LaravelArazzo\Generator\PromptBuilder;
use Alama\LaravelArazzo\Resolution\SourceResolver;
use Alama\LaravelArazzo\Resolution\OpenApiResolvedSource;
use Alama\LaravelArazzo\Dto\SourceDescription;
use cebe\openapi\Reader;

it('builds prompt array', function () {
    $openapi = Reader::readFromJson('{"openapi": "3.0.0", "paths": {"/test": {"get": {"operationId": "getTest"}}}}');
    $resolved = new OpenApiResolvedSource($openapi);
    
    // Mock the resolver to return our fake OpenAPI source
    $resolver = Mockery::mock(SourceResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($resolved);
    
    config(['arazzo.generator.system_prompt' => 'System Instruction']);
    
    $builder = new PromptBuilder($resolver);
    $messages = $builder->build('/fake/path.yaml', 'Create a test workflow');
    
    expect($messages)->toHaveCount(2);
    expect($messages[0]['role'])->toBe('system');
    expect($messages[0]['content'])->toContain('System Instruction');
    expect($messages[1]['role'])->toBe('user');
    expect($messages[1]['content'])->toContain('Create a test workflow');
    expect($messages[1]['content'])->toContain('/test');
    expect($messages[1]['content'])->toContain('getTest');
});
```

- [ ] **Step 2: Implement PromptBuilder**

```php
// src/Generator/PromptBuilder.php
namespace Alama\LaravelArazzo\Generator;

use Alama\LaravelArazzo\Resolution\SourceResolver;
use Alama\LaravelArazzo\Resolution\OpenApiResolvedSource;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\Enum\SourceType;

class PromptBuilder
{
    public function __construct(private SourceResolver $resolver) {}

    public function build(string $openApiPath, string $goal): array
    {
        $source = new SourceDescription('api', $openApiPath, SourceType::Openapi);
        $resolved = $this->resolver->resolve($source, getcwd());
        
        $apiSummary = $this->summarizeApi($resolved);
        
        $systemPrompt = config('arazzo.generator.system_prompt');
        
        $userContent = "Goal: {$goal}\n\nAvailable Endpoints:\n{$apiSummary}\n\nPlease generate the YAML.";

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userContent],
        ];
    }
    
    private function summarizeApi(OpenApiResolvedSource $resolved): string
    {
        // Extract paths and operations from the cebe OpenApi object for the prompt context
        // This is a basic implementation to extract operationIds
        $summary = [];
        $data = $resolved->extract('/'); // Get root object
        
        if (isset($data['paths']) && is_array($data['paths'])) {
            foreach ($data['paths'] as $path => $methods) {
                foreach ((array)$methods as $method => $op) {
                    if (is_array($op) && isset($op['operationId'])) {
                        $summary[] = "- [{$method}] {$path} (operationId: {$op['operationId']})";
                    }
                }
            }
        }
        
        return implode("\n", $summary);
    }
}
```

- [ ] **Step 3: Run test**

Run: `rtk php artisan test --filter PromptBuilderTest`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
rtk git add src/Generator/PromptBuilder.php tests/Generator/PromptBuilderTest.php
rtk git commit -m "feat: implement PromptBuilder to format OpenAPI context"
```

---

### Task 4: Implement ArazzoGenerator Orchestrator

**Files:**
- Create: `src/Generator/ArazzoGenerator.php`
- Create: `tests/Generator/ArazzoGeneratorTest.php`

- [ ] **Step 1: Write test for Orchestrator**

```php
// tests/Generator/ArazzoGeneratorTest.php
use Alama\LaravelArazzo\Generator\ArazzoGenerator;
use Alama\LaravelArazzo\Generator\LlmDriver;
use Alama\LaravelArazzo\Generator\PromptBuilder;
use Alama\LaravelArazzo\Arazzo; // Facade
use Alama\LaravelArazzo\Validation\ValidationResult;
use Alama\LaravelArazzo\Validation\Error;
use Alama\LaravelArazzo\Dto\ArazzoDocument;

it('generates valid yaml on first try', function () {
    $driver = Mockery::mock(LlmDriver::class);
    $driver->shouldReceive('chat')->once()->andReturn('valid_yaml');
    
    $builder = Mockery::mock(PromptBuilder::class);
    $builder->shouldReceive('build')->andReturn([['role' => 'user', 'content' => 'test']]);
    
    // Mock the core Validator
    Arazzo::shouldReceive('validate')->once()->andReturn(
        new ValidationResult(Mockery::mock(ArazzoDocument::class), [], [])
    );
    
    config(['arazzo.generator.max_retries' => 3]);
    
    $generator = new ArazzoGenerator($driver, $builder);
    $result = $generator->generate('api.yaml', 'test goal');
    
    expect($result)->toBe('valid_yaml');
});
```

- [ ] **Step 2: Implement ArazzoGenerator**

```php
// src/Generator/ArazzoGenerator.php
namespace Alama\LaravelArazzo\Generator;

use Alama\LaravelArazzo\Facades\Arazzo;
use Alama\LaravelArazzo\Generator\Exceptions\GenerationException;

class ArazzoGenerator
{
    public function __construct(
        private LlmDriver $driver,
        private PromptBuilder $builder
    ) {}

    public function generate(string $openApiPath, string $goal): string
    {
        $messages = $this->builder->build($openApiPath, $goal);
        $maxRetries = config('arazzo.generator.max_retries', 3);
        $attempts = 0;
        $lastYaml = '';

        while ($attempts < $maxRetries) {
            $lastYaml = $this->driver->chat($messages);
            $lastYaml = $this->cleanYaml($lastYaml); // remove markdown blocks if any
            
            // Write to a temporary file to use the loader
            $tempFile = tempnam(sys_get_temp_dir(), 'arazzo_');
            file_put_contents($tempFile, $lastYaml);
            
            try {
                $result = Arazzo::validate($tempFile);
                if ($result->isValid()) {
                    @unlink($tempFile);
                    return $lastYaml;
                }
                
                // Format errors
                $errorMsg = "Your document failed validation. Fix these errors and return the full YAML:\n";
                foreach ($result->errors as $error) {
                    $errorMsg .= "- [{$error->path}] {$error->code}: {$error->message}\n";
                }
                
                $messages[] = ['role' => 'assistant', 'content' => $lastYaml];
                $messages[] = ['role' => 'user', 'content' => $errorMsg];
                
            } catch (\Exception $e) {
                // If Loader or Parser completely failed (e.g. malformed YAML)
                $messages[] = ['role' => 'assistant', 'content' => $lastYaml];
                $messages[] = ['role' => 'user', 'content' => "Failed to parse YAML: " . $e->getMessage()];
            } finally {
                @unlink($tempFile);
            }
            
            $attempts++;
        }

        throw new GenerationException("Failed to generate a valid Arazzo spec after $maxRetries attempts. Last output: \n$lastYaml");
    }
    
    private function cleanYaml(string $output): string
    {
        // Strip markdown code block wrappers if the LLM includes them
        $output = preg_replace('/^```yaml\s*/im', '', $output);
        $output = preg_replace('/```$/m', '', $output);
        return trim($output);
    }
}
```

- [ ] **Step 3: Run tests**

Run: `rtk php artisan test --filter ArazzoGeneratorTest`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
rtk git add src/Generator/ArazzoGenerator.php tests/Generator/ArazzoGeneratorTest.php
rtk git commit -m "feat: implement ArazzoGenerator orchestrator with reflection loop"
```

---

### Task 5: Implement CLI Command

**Files:**
- Create: `src/Commands/GenerateArazzoCommand.php`
- Modify: `src/LaravelArazzoServiceProvider.php` (register command and bindings)

- [ ] **Step 1: Create Command**

```php
// src/Commands/GenerateArazzoCommand.php
namespace Alama\LaravelArazzo\Commands;

use Illuminate\Console\Command;
use Alama\LaravelArazzo\Generator\ArazzoGenerator;
use Alama\LaravelArazzo\Generator\Exceptions\GenerationException;

class GenerateArazzoCommand extends Command
{
    protected $signature = 'arazzo:generate {openapi : Path or URL to OpenAPI spec} {--goal= : Natural language goal} {--output= : Path to save generated YAML}';
    protected $description = 'Generate an Arazzo workflow YAML using AI based on an OpenAPI spec.';

    public function handle(ArazzoGenerator $generator): int
    {
        $openapi = $this->argument('openapi');
        $goal = $this->option('goal');
        $output = $this->option('output');

        if (!$goal) {
            $goal = $this->ask('What is the goal of this workflow?');
        }

        $this->components->info("Generating Arazzo workflow for goal: $goal");

        try {
            $yaml = $generator->generate($openapi, $goal);
            
            if ($output) {
                file_put_contents($output, $yaml);
                $this->components->info("Workflow saved to $output");
            } else {
                $this->line($yaml);
            }
            
            return self::SUCCESS;
        } catch (GenerationException $e) {
            $this->components->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
```

- [ ] **Step 2: Update Service Provider**

In `src/LaravelArazzoServiceProvider.php`, register the generator bindings:

```php
public function packageRegistered(): void
{
    // ... existing bindings ...
    
    $this->app->singleton(\Alama\LaravelArazzo\Generator\LlmDriver::class, function () {
        // Here you would resolve the driver based on config('arazzo.generator.driver')
        // For now, default to DefaultHttpDriver
        return new \Alama\LaravelArazzo\Generator\Drivers\DefaultHttpDriver();
    });
    
    $this->app->singleton(\Alama\LaravelArazzo\Generator\ArazzoGenerator::class);
}

public function configurePackage(Package $package): void
{
    $package
        ->name('laravel-arazzo')
        ->hasConfigFile()
        ->hasCommands([
            ValidateArazzoCommand::class,
            GenerateArazzoCommand::class, // Add this
        ]);
}
```

- [ ] **Step 3: Commit**

```bash
rtk git add src/Commands/GenerateArazzoCommand.php src/LaravelArazzoServiceProvider.php
rtk git commit -m "feat: add artisan command for arazzo generation"
```
