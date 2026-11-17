<?php

declare(strict_types=1);

use Alama\Arazzo\Cli\Console\Command\RunCommand;
use Alama\Arazzo\Cli\Console\DocumentLoader;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDocument;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Protocol\Http\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Protocol\Http\Execution\ResponseSchemaValidator;
use Alama\Arazzo\Protocol\Http\Execution\StepOutputExtractor;
use Alama\Arazzo\Runner\RunnerFacade;
use Alama\Arazzo\Sources\Resolver\SourceRegistry;
use Alama\Arazzo\Sources\SourceGraph;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Tester\CommandTester;

const PETSTORE_MINI = [
    'openapi' => '3.0.3',
    'info' => ['title' => 'Mini', 'version' => '1'],
    'servers' => [['url' => 'https://mini.test']],
    'paths' => [
        '/ping' => [
            'get' => [
                'operationId' => 'ping',
                'responses' => ['200' => ['description' => 'ok']],
            ],
        ],
    ],
];

function arazzoDoc(string $tmp): void
{
    file_put_contents($tmp, <<<'YAML'
        arazzo: "1.0.0"
        info:
          title: Run CLI
          version: "1"
        sourceDescriptions:
          - name: api
            url: https://mini.test/openapi.json
            type: openapi
        workflows:
          - workflowId: pingFlow
            steps:
              - stepId: ping
                operationPath: "{$sourceDescriptions.api.url}#/paths/~1ping/get"
                successCriteria:
                  - condition: "${response.statusCode} == 200"
        YAML);
}

function createTestRegistry(ClientInterface $client, RequestFactoryInterface $factory): SourceRegistry
{
    return SourceGraph::createRegistry($client, $factory);
}

it('runs a workflow end-to-end through the CLI', function (): void {
    // PSR-18 client that answers every request with 200 {}
    $client = new class() implements ClientInterface
    {
        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            return new Response(200, ['Content-Type' => 'application/json'], '{}');
        }
    };
    $factory = new HttpFactory();

    $registry = createTestRegistry($client, $factory);
    $registry->register(new SourceDocument('api', SourceType::Openapi, 'https://mini.test/openapi.json', PETSTORE_MINI));

    $command = new RunCommand($client, $factory, $registry);
    $tester = new CommandTester($command);

    $doc = sys_get_temp_dir().'/arazzo-cli-run-'.uniqid().'.yaml';
    arazzoDoc($doc);

    $tester->execute(['file' => $doc]);

    unlink($doc);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('pingFlow: succeeded')
        ->and($tester->getDisplay())->toContain('✔ ping');
});

it('fails with a clear message for an unknown workflow id', function (): void {
    $client = new class() implements ClientInterface
    {
        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            return new Response(200, ['Content-Type' => 'application/json'], '{}');
        }
    };
    $factory = new HttpFactory();

    $registry = createTestRegistry($client, $factory);
    $registry->register(new SourceDocument('api', SourceType::Openapi, 'https://mini.test/openapi.json', PETSTORE_MINI));

    $command = new RunCommand($client, $factory, $registry);
    $tester = new CommandTester($command);

    $doc = sys_get_temp_dir().'/arazzo-cli-unknown-'.uniqid().'.yaml';
    arazzoDoc($doc);

    $tester->execute(['file' => $doc, '--workflow' => 'nope']);

    unlink($doc);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('unknown workflow');
});

it('exposes the facade result shape the CLI output rendering depends on', function (): void {
    $client = new class() implements ClientInterface
    {
        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            return new Response(200, ['Content-Type' => 'application/json'], '{}');
        }
    };
    $factory = new HttpFactory();

    $registry = createTestRegistry($client, $factory);
    $registry->register(new SourceDocument('api', SourceType::Openapi, 'https://mini.test/openapi.json', PETSTORE_MINI));

    $doc = sys_get_temp_dir().'/arazzo-cli-shape-'.uniqid().'.yaml';
    arazzoDoc($doc);

    $document = DocumentLoader::load($doc);
    $runtime = SourceGraph::runtime(registry: $registry);
    $engine = new EvaluationEngine(expression: new ExpressionEngine());
    $runner = new RunnerFacade(
        $runtime->document,
        $runtime->operations,
        $engine,
        openApiExecutor: new DefaultOpenApiExecutor($client, $factory),
        outputExtractor: new StepOutputExtractor($runtime->operations, $engine, new ExpressionEngine()),
        schemaValidator: new ResponseSchemaValidator($runtime->operations),
    );

    unlink($doc);

    $result = $runner->execute($document, 'pingFlow');

    expect($result['status'])->toBe('succeeded')
        ->and($result['steps'])->toHaveKey('ping')
        ->and($result['steps']['ping'])->toMatchArray(['stepId' => 'ping', 'success' => true, 'error' => null]);
});

function inputsDoc(string $workflowId, string $schemaYaml = '', array $steps = []): string
{
    if ($steps === []) {
        $steps = [
            'fetch' => [
                'operationPath' => '{$sourceDescriptions.api.url}#/paths/~1ping/get',
            ],
        ];
    }

    $stepsYaml = '';
    foreach ($steps as $stepId => $step) {
        $stepsYaml .= "      - stepId: {$stepId}\n";
        foreach ($step as $key => $value) {
            $quoted = str_starts_with($value, '{') || str_contains($value, '#') || str_contains($value, ':') ? "'".$value."'" : $value;
            $stepsYaml .= "        {$key}: {$quoted}\n";
        }
    }

    $schemaBlock = $schemaYaml === '' ? '' : "    inputs:\n".$schemaYaml;

    $tmp = sys_get_temp_dir().'/arazzo-cli-inputs-'.uniqid().'.yaml';
    file_put_contents($tmp, <<<YAML
arazzo: "1.0.0"
info:
  title: Inputs CLI
  version: "1"
sourceDescriptions:
  - name: api
    url: https://mini.test/openapi.json
    type: openapi
workflows:
  - workflowId: {$workflowId}
{$schemaBlock}
    steps:
{$stepsYaml}
YAML);

    return $tmp;
}

function cliCommand(ClientInterface $client, RequestFactoryInterface $factory, SourceRegistry $registry): RunCommand
{
    return new RunCommand($client, $factory, $registry);
}

function okClient(): ClientInterface
{
    return new class() implements ClientInterface
    {
        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            return new Response(200, ['Content-Type' => 'application/json'], '{}');
        }
    };
}

it('validates inputs before execution (S1)', function (): void {
    $client = okClient();
    $factory = new HttpFactory();
    $registry = createTestRegistry($client, $factory);
    $registry->register(new SourceDocument('api', SourceType::Openapi, 'https://mini.test/openapi.json', PETSTORE_MINI));

    $doc = inputsDoc('create', "      type: object\n      required: [username]\n      properties:\n        username:\n          type: string");

    $command = cliCommand($client, $factory, $registry);
    $tester = new CommandTester($command);

    $tester->execute(['file' => $doc, '--input' => '{"username": "alice"}']);

    unlink($doc);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('create: succeeded');
});

it('fails on missing required input (S2)', function (): void {
    $client = okClient();
    $factory = new HttpFactory();
    $registry = createTestRegistry($client, $factory);
    $registry->register(new SourceDocument('api', SourceType::Openapi, 'https://mini.test/openapi.json', PETSTORE_MINI));

    $doc = inputsDoc('create', "      type: object\n      required: [username]\n      properties:\n        username:\n          type: string");

    $command = cliCommand($client, $factory, $registry);
    $tester = new CommandTester($command);

    $tester->execute(['file' => $doc, '--input' => '{"retries": 5}']);

    unlink($doc);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('username');
});

it('fails on type mismatch (S3)', function (): void {
    $client = okClient();
    $factory = new HttpFactory();
    $registry = createTestRegistry($client, $factory);
    $registry->register(new SourceDocument('api', SourceType::Openapi, 'https://mini.test/openapi.json', PETSTORE_MINI));

    $doc = inputsDoc('create', "      type: object\n      properties:\n        timeout:\n          type: integer");

    $command = cliCommand($client, $factory, $registry);
    $tester = new CommandTester($command);

    $tester->execute(['file' => $doc, '--input' => '{"timeout": "thirty"}']);

    unlink($doc);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('integer');
});

it('fails on enum violation (S4)', function (): void {
    $client = okClient();
    $factory = new HttpFactory();
    $registry = createTestRegistry($client, $factory);
    $registry->register(new SourceDocument('api', SourceType::Openapi, 'https://mini.test/openapi.json', PETSTORE_MINI));

    $doc = inputsDoc('create', "      type: object\n      properties:\n        region:\n          type: string\n          enum: [eu, us]");

    $command = cliCommand($client, $factory, $registry);
    $tester = new CommandTester($command);

    $tester->execute(['file' => $doc, '--input' => '{"region": "mars"}']);

    unlink($doc);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('enumeration');
});

it('applies schema defaults from validated map (S5)', function (): void {
    $client = okClient();
    $factory = new HttpFactory();
    $registry = createTestRegistry($client, $factory);
    $registry->register(new SourceDocument('api', SourceType::Openapi, 'https://mini.test/openapi.json', PETSTORE_MINI));

    $doc = inputsDoc('create', "      type: object\n      required: [username]\n      properties:\n        username:\n          type: string\n        retries:\n          type: integer\n          default: 3");

    $command = cliCommand($client, $factory, $registry);
    $tester = new CommandTester($command);

    // retries omitted; default 3 should be injected by validation
    $tester->execute(['file' => $doc, '--input' => '{"username": "alice"}']);

    unlink($doc);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('create: succeeded');
});

it('accepts arbitrary inputs when no schema declared (S6)', function (): void {
    $client = okClient();
    $factory = new HttpFactory();
    $registry = createTestRegistry($client, $factory);
    $registry->register(new SourceDocument('api', SourceType::Openapi, 'https://mini.test/openapi.json', PETSTORE_MINI));

    $doc = inputsDoc('create', '', [
        'create' => [
            'operationPath' => '{$sourceDescriptions.api.url}#/paths/~1ping/get',
        ],
    ]);

    $command = cliCommand($client, $factory, $registry);
    $tester = new CommandTester($command);

    $tester->execute(['file' => $doc, '--input' => '{"anything": "goes"}']);

    unlink($doc);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('create: succeeded');
});

/**
 * The HTTP boundary fake: records every real request the graph builds.
 */
final class CliRecordingHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @param list<ResponseInterface> $responses */
    public function __construct(private array $responses = []) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return array_shift($this->responses) ?? new Response(200, ['Content-Type' => 'application/json'], '{}');
    }
}

const REAL_HTTP_OPENAPI = [
    'openapi' => '3.0.3',
    'info' => ['title' => 'Pet API', 'version' => '1'],
    'servers' => [['url' => 'https://mini.test']],
    'paths' => [
        '/pets' => [
            'get' => [
                'operationId' => 'listPets',
                'responses' => [
                    '200' => [
                        'description' => 'ok',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['pets'],
                                    'properties' => [
                                        'pets' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/pets/{petId}' => [
            'get' => [
                'operationId' => 'getPet',
                'parameters' => [
                    ['name' => 'petId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'ok',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['id'],
                                    'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'post' => [
                'operationId' => 'updatePet',
                'parameters' => [
                    ['name' => 'petId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ['name' => 'X-Trace', 'in' => 'header', 'schema' => ['type' => 'string']],
                ],
                'requestBody' => [
                    'required' => true,
                    'content' => ['application/json' => ['schema' => ['type' => 'object']]],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'ok',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => ['id' => ['type' => 'integer']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

function realHttpDoc(string $workflow): string
{
    $doc = sys_get_temp_dir().'/arazzo-cli-http-'.uniqid().'.yaml';
    file_put_contents($doc, $workflow);

    return $doc;
}

function realHttpRegistry(ClientInterface $client, RequestFactoryInterface $factory): SourceRegistry
{
    $registry = createTestRegistry($client, $factory);
    $registry->register(new SourceDocument('api', SourceType::Openapi, 'https://mini.test/openapi.json', REAL_HTTP_OPENAPI));

    return $registry;
}

it('sends the real request through the injected HTTP client (method, URL, path, query, header, body)', function (): void {
    $client = new CliRecordingHttpClient([
        new Response(200, ['Content-Type' => 'application/json'], '{"id": 7}'),
    ]);
    $factory = new HttpFactory();

    $doc = realHttpDoc(<<<'YAML'
        arazzo: "1.0.0"
        info:
          title: Real HTTP
          version: "1"
        sourceDescriptions:
          - name: api
            url: https://mini.test/openapi.json
            type: openapi
        workflows:
          - workflowId: updatePet
            steps:
              - stepId: update
                operationPath: "{$sourceDescriptions.api.url}#/paths/~1pets~1{petId}/post"
                parameters:
                  - name: petId
                    in: path
                    value: 42
                  - name: limit
                    in: query
                    value: 10
                  - name: X-Trace
                    in: header
                    value: trace-1
                requestBody:
                  contentType: application/json
                  payload:
                    name: Fido
                    age: 3
                successCriteria:
                  - condition: $statusCode == 200
        YAML);

    $tester = new CommandTester(new RunCommand($client, $factory, realHttpRegistry($client, $factory)));
    $tester->execute(['file' => $doc]);

    unlink($doc);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('updatePet: succeeded')
        ->and($client->requests)->toHaveCount(1);

    $request = $client->requests[0];

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://mini.test/pets/42?limit=10')
        ->and($request->getHeaderLine('X-Trace'))->toBe('trace-1')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and(json_decode((string) $request->getBody(), true))->toBe(['name' => 'Fido', 'age' => 3]);
});

it('exposes step outputs to downstream steps and workflow outputs', function (): void {
    $client = new CliRecordingHttpClient([
        new Response(200, ['Content-Type' => 'application/json'], '{"pets": [{"id": 7, "name": "Fido"}]}'),
        new Response(200, ['Content-Type' => 'application/json'], '{"id": 7, "name": "Fido"}'),
    ]);
    $factory = new HttpFactory();

    $doc = realHttpDoc(<<<'YAML'
        arazzo: "1.0.0"
        info:
          title: Real HTTP outputs
          version: "1"
        sourceDescriptions:
          - name: api
            url: https://mini.test/openapi.json
            type: openapi
        workflows:
          - workflowId: petFlow
            outputs:
              petId: "{$steps.list.outputs.petId}"
            steps:
              - stepId: list
                operationPath: "{$sourceDescriptions.api.url}#/paths/~1pets/get"
                outputs:
                  petId: "{$response.body#/pets/0/id}"
                successCriteria:
                  - condition: $statusCode == 200
              - stepId: fetch
                operationPath: "{$sourceDescriptions.api.url}#/paths/~1pets~1{petId}/get"
                parameters:
                  - name: petId
                    in: path
                    value: "{$steps.list.outputs.petId}"
                successCriteria:
                  - condition: $statusCode == 200
        YAML);

    $tester = new CommandTester(new RunCommand($client, $factory, realHttpRegistry($client, $factory)));
    $tester->execute(['file' => $doc]);

    unlink($doc);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('petFlow: succeeded')
        ->and($tester->getDisplay())->toContain('✔ list')
        ->and($tester->getDisplay())->toContain('✔ fetch')
        ->and($tester->getDisplay())->toContain('petId = 7')
        ->and($client->requests)->toHaveCount(2)
        ->and((string) $client->requests[1]->getUri())->toBe('https://mini.test/pets/7');
});

it('fails the run and renders the violation when a response breaks its schema', function (): void {
    $client = new CliRecordingHttpClient([
        new Response(200, ['Content-Type' => 'application/json'], '{"id": "not-an-integer"}'),
    ]);
    $factory = new HttpFactory();

    $doc = realHttpDoc(<<<'YAML'
        arazzo: "1.0.0"
        info:
          title: Real HTTP schema
          version: "1"
        sourceDescriptions:
          - name: api
            url: https://mini.test/openapi.json
            type: openapi
        workflows:
          - workflowId: petFlow
            steps:
              - stepId: fetch
                operationPath: "{$sourceDescriptions.api.url}#/paths/~1pets~1{petId}/get"
                parameters:
                  - name: petId
                    in: path
                    value: 7
                successCriteria:
                  - condition: $statusCode == 200
        YAML);

    $tester = new CommandTester(new RunCommand($client, $factory, realHttpRegistry($client, $factory)));
    $tester->execute(['file' => $doc]);

    unlink($doc);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain("Response schema validation failed for step 'fetch'")
        ->and($tester->getDisplay())->toContain('/id')
        ->and($tester->getDisplay())->toContain("expected type 'integer'");
});

it('fails the run when success criteria are not met', function (): void {
    $client = new CliRecordingHttpClient([
        new Response(500, ['Content-Type' => 'application/json'], '{}'),
    ]);
    $factory = new HttpFactory();

    $doc = realHttpDoc(<<<'YAML'
        arazzo: "1.0.0"
        info:
          title: Real HTTP criteria
          version: "1"
        sourceDescriptions:
          - name: api
            url: https://mini.test/openapi.json
            type: openapi
        workflows:
          - workflowId: petFlow
            steps:
              - stepId: fetch
                operationPath: "{$sourceDescriptions.api.url}#/paths/~1pets~1{petId}/get"
                parameters:
                  - name: petId
                    in: path
                    value: 7
                successCriteria:
                  - condition: $statusCode == 200
        YAML);

    $tester = new CommandTester(new RunCommand($client, $factory, realHttpRegistry($client, $factory)));
    $tester->execute(['file' => $doc]);

    unlink($doc);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('petFlow: failed')
        ->and($tester->getDisplay())->toContain('✘ fetch');
});
