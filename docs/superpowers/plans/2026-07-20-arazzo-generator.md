# ArazzoGenerator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the AI backend logic (`ArazzoGenerator`) that converts a natural language trace into a valid Arazzo YAML document.

**Architecture:** We will create an `AiClientInterface` to abstract the LLM provider, providing a PSR-18 compliant `OpenAiClient` implementation. `ArazzoGenerator` will use this interface to inject a system prompt containing the OpenAPI context and the Arazzo specification rules, along with the user's workflow trace, returning the AI-generated YAML.

**Tech Stack:** PHP 8.2+, PSR-18 (HTTP Client), PSR-17 (HTTP Factories)

---

### Task 1: AI Client Interface and Basic Client

**Files:**
- Create: `src/Generator/Contracts/AiClientInterface.php`
- Create: `src/Generator/Clients/OpenAiClient.php`
- Create: `tests/Generator/Clients/OpenAiClientTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Generator/Clients/OpenAiClientTest.php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Generator\Clients;

use Alama\LaravelArazzo\Generator\Clients\OpenAiClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

it('sends prompt to openai and returns content', function () {
    $requestFactory = new class implements RequestFactoryInterface {
        public function createRequest(string $method, $uri): RequestInterface {
            return new class($method, (string)$uri) implements RequestInterface {
                private array $headers = [];
                public function __construct(public string $method, public string $uri) {}
                public function getProtocolVersion(): string { return '1.1'; }
                public function withProtocolVersion($version): RequestInterface { return $this; }
                public function getHeaders(): array { return $this->headers; }
                public function hasHeader($name): bool { return isset($this->headers[$name]); }
                public function getHeader($name): array { return $this->headers[$name] ?? []; }
                public function getHeaderLine($name): string { return implode(', ', $this->getHeader($name)); }
                public function withHeader($name, $value): RequestInterface { $c = clone $this; $c->headers[$name] = (array)$value; return $c; }
                public function withAddedHeader($name, $value): RequestInterface { return $this; }
                public function withoutHeader($name): RequestInterface { return $this; }
                public function getBody(): StreamInterface { throw new \Exception(); }
                public function withBody(StreamInterface $body): RequestInterface { return $this; }
                public function getRequestTarget(): string { return ''; }
                public function withRequestTarget($requestTarget): RequestInterface { return $this; }
                public function getMethod(): string { return $this->method; }
                public function withMethod($method): RequestInterface { return $this; }
                public function getUri(): \Psr\Http\Message\UriInterface { throw new \Exception(); }
                public function withUri(\Psr\Http\Message\UriInterface $uri, $preserveHost = false): RequestInterface { return $this; }
            };
        }
    };

    $responseMock = new class implements ResponseInterface {
        public function getStatusCode(): int { return 200; }
        public function withStatus($code, $reasonPhrase = ''): ResponseInterface { return $this; }
        public function getReasonPhrase(): string { return 'OK'; }
        public function getProtocolVersion(): string { return '1.1'; }
        public function withProtocolVersion($version): ResponseInterface { return $this; }
        public function getHeaders(): array { return []; }
        public function hasHeader($name): bool { return false; }
        public function getHeader($name): array { return []; }
        public function getHeaderLine($name): string { return ''; }
        public function withHeader($name, $value): ResponseInterface { return $this; }
        public function withAddedHeader($name, $value): ResponseInterface { return $this; }
        public function withoutHeader($name): ResponseInterface { return $this; }
        public function getBody(): StreamInterface {
            return new class implements StreamInterface {
                public function __toString(): string { 
                    return json_encode([
                        'choices' => [
                            ['message' => ['content' => 'generated_yaml']]
                        ]
                    ]); 
                }
                public function close(): void {}
                public function detach() {}
                public function getSize(): ?int { return null; }
                public function tell(): int { return 0; }
                public function eof(): bool { return true; }
                public function isSeekable(): bool { return false; }
                public function seek($offset, $whence = \SEEK_SET): void {}
                public function rewind(): void {}
                public function isWritable(): bool { return false; }
                public function write($string): int { return 0; }
                public function isReadable(): bool { return true; }
                public function read($length): string { return ''; }
                public function getContents(): string { return $this->__toString(); }
                public function getMetadata($key = null) { return null; }
            };
        }
        public function withBody(StreamInterface $body): ResponseInterface { return $this; }
    };

    $httpClient = new class($responseMock) implements ClientInterface {
        public array $requests = [];
        public function __construct(private ResponseInterface $response) {}
        public function sendRequest(RequestInterface $request): ResponseInterface {
            $this->requests[] = $request;
            return $this->response;
        }
    };

    $client = new OpenAiClient($httpClient, $requestFactory, 'test-key', 'gpt-4o');
    $result = $client->generate('system_instructions', 'user_trace');

    expect($result)->toBe('generated_yaml');
    expect($httpClient->requests)->toHaveCount(1);
    
    /** @var RequestInterface $req */
    $req = $httpClient->requests[0];
    expect($req->getMethod())->toBe('POST');
    expect($req->getHeaderLine('Authorization'))->toBe('Bearer test-key');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `rtk proxy herd php vendor/bin/pest tests/Generator/Clients/OpenAiClientTest.php`
Expected: FAIL (Class `OpenAiClient` not found)

- [ ] **Step 3: Write minimal implementation**

```php
// src/Generator/Contracts/AiClientInterface.php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Generator\Contracts;

interface AiClientInterface
{
    public function generate(string $systemPrompt, string $userPrompt): string;
}
```

```php
// src/Generator/Clients/OpenAiClient.php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Generator\Clients;

use Alama\LaravelArazzo\Generator\Contracts\AiClientInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use RuntimeException;

class OpenAiClient implements AiClientInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private string $apiKey,
        private string $model = 'gpt-4o',
        private string $endpoint = 'https://api.openai.com/v1/chat/completions'
    ) {}

    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $request = $this->requestFactory->createRequest('POST', $this->endpoint)
            ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json');

        // Note: For full PSR-17 compliance, we should use a StreamFactory to set the body.
        // However, some PSR-18 clients in Laravel don't strictly require StreamFactory if we are just testing.
        // For actual robust PSR-18 usage, a StreamFactory is required.
        // To keep it simple and strictly passing our mock, we will assume the request mock in Pest 
        // doesn't enforce strict body attachment via StreamFactory, but actually we need to write the body.
        // If StreamFactory is not injected, we'll try to write to a temp stream if available.
        
        $bodyData = json_encode([
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.1,
        ]);

        // Hacky fallback for PSR-7 Body without StreamFactory injected
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $bodyData);
        rewind($stream);
        
        // We'll wrap the stream in a basic PSR-7 stream class since we didn't inject StreamFactory
        // In a real app we should use PSR-17 StreamFactory.
        $psrStream = new class($stream) implements \Psr\Http\Message\StreamInterface {
            public function __construct(private $stream) {}
            public function __toString(): string { rewind($this->stream); return stream_get_contents($this->stream); }
            public function close(): void { fclose($this->stream); }
            public function detach() { $res = $this->stream; $this->stream = null; return $res; }
            public function getSize(): ?int { return null; }
            public function tell(): int { return ftell($this->stream); }
            public function eof(): bool { return feof($this->stream); }
            public function isSeekable(): bool { return true; }
            public function seek($offset, $whence = \SEEK_SET): void { fseek($this->stream, $offset, $whence); }
            public function rewind(): void { rewind($this->stream); }
            public function isWritable(): bool { return true; }
            public function write($string): int { return fwrite($this->stream, $string); }
            public function isReadable(): bool { return true; }
            public function read($length): string { return fread($this->stream, $length); }
            public function getContents(): string { return stream_get_contents($this->stream); }
            public function getMetadata($key = null) { return null; }
        };

        $request = $request->withBody($psrStream);

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException('AI API request failed: ' . (string) $response->getBody());
        }

        $data = json_decode((string) $response->getBody(), true);
        
        return $data['choices'][0]['message']['content'] ?? '';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `rtk proxy herd php vendor/bin/pest tests/Generator/Clients/OpenAiClientTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
rtk git add tests/Generator/ src/Generator/
rtk git commit -m "feat: implement OpenAiClient using PSR-18"
```

---

### Task 2: ArazzoGenerator

**Files:**
- Create: `src/Generator/ArazzoGenerator.php`
- Create: `tests/Generator/ArazzoGeneratorTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Generator/ArazzoGeneratorTest.php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Generator;

use Alama\LaravelArazzo\Generator\ArazzoGenerator;
use Alama\LaravelArazzo\Generator\Contracts\AiClientInterface;

it('generates arazzo yaml from openapi and trace', function () {
    $aiClient = new class implements AiClientInterface {
        public string $lastSystemPrompt = '';
        public string $lastUserPrompt = '';
        public function generate(string $systemPrompt, string $userPrompt): string {
            $this->lastSystemPrompt = $systemPrompt;
            $this->lastUserPrompt = $userPrompt;
            return "arazzo: 1.0.1\ninfo:\n  title: Test\n  version: 1.0.0";
        }
    };

    $generator = new ArazzoGenerator($aiClient);
    
    $openapi = "openapi: 3.0.0\ninfo:\n  title: API\n  version: 1.0";
    $trace = "Workflow Graph Intent:\n- Node 1: Execute GET /users";

    $yaml = $generator->generate($openapi, $trace);

    expect($yaml)->toContain('arazzo: 1.0.1');
    expect($aiClient->lastSystemPrompt)->toContain('You are an expert Arazzo specification generator');
    expect($aiClient->lastUserPrompt)->toContain($trace);
    expect($aiClient->lastUserPrompt)->toContain($openapi);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `rtk proxy herd php vendor/bin/pest tests/Generator/ArazzoGeneratorTest.php`
Expected: FAIL (Class `ArazzoGenerator` not found)

- [ ] **Step 3: Write minimal implementation**

```php
// src/Generator/ArazzoGenerator.php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Generator;

use Alama\LaravelArazzo\Generator\Contracts\AiClientInterface;

class ArazzoGenerator
{
    public function __construct(private AiClientInterface $aiClient) {}

    public function generate(string $openapiSpec, string $workflowTrace): string
    {
        $systemPrompt = <<<PROMPT
You are an expert Arazzo specification generator. Arazzo is an OpenAPI-adjacent specification for describing workflows of sequential API calls.
Your goal is to output a strictly valid Arazzo (1.0.1) YAML document based on the user's provided OpenAPI specification and workflow trace.

Rules for Arazzo Generation:
1. Always start the document with `arazzo: 1.0.1` and an `info` block containing `title` and `version`.
2. Define `sourceDescriptions` linking to the OpenAPI (e.g., `name: api`, `type: openapi`).
3. Define `workflows` containing a `workflowId` and `steps`.
4. In each step, use `operationId` corresponding to the OpenAPI spec.
5. Extract values between steps using expressions (e.g., `{\$steps.step1.response.body#/data/id}`).
6. Use parameters passing values (e.g., `in: path`, `name: userId`, `value: {\$steps.step1.outputs.userId}`).
7. Output only the YAML block. Do not include markdown fences like ```yaml.
PROMPT;

        $userPrompt = <<<USER
Below is the OpenAPI Specification context:
{$openapiSpec}

Here is the intended Workflow Trace mapping out the sequence of steps:
{$workflowTrace}

Please generate the Arazzo YAML document:
USER;

        $yaml = $this->aiClient->generate($systemPrompt, $userPrompt);
        
        // Strip out markdown code blocks if the AI returned them despite the prompt
        $yaml = preg_replace('/^```yaml\s*|\s*```$/si', '', trim($yaml));
        
        return $yaml;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `rtk proxy herd php vendor/bin/pest tests/Generator/ArazzoGeneratorTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
rtk git add tests/Generator/ArazzoGeneratorTest.php src/Generator/ArazzoGenerator.php
rtk git commit -m "feat: implement ArazzoGenerator for AI-based yaml creation"
```
