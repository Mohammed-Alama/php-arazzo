<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests;

use Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\SourceDocument;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Protocol\Http\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Protocol\Http\Execution\ResponseSchemaValidator;
use Alama\Arazzo\Protocol\Http\Execution\StepOutputExtractor;
use Alama\Arazzo\Protocol\Http\Protocol\AsyncApiStepExecutor;
use Alama\Arazzo\Protocol\Http\Protocol\HttpStepExecutor;
use Alama\Arazzo\Protocol\Http\Registrar\ProtocolHttpRegistrar;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use Alama\Arazzo\Runner\OperationExecutorRegistry;
use Alama\Arazzo\Runner\ResponseValidatorDispatcher;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Sources\Normalizer\OpenApiSourceNormalizer;
use Alama\Arazzo\Sources\Resolver\SourceRegistry;
use Alama\Arazzo\Sources\Validator\PreflightValidator;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionProperty;

/**
 * F1.3: the composed app works with zero config — the protocol-http value
 * seams resolve to their implementations, the plugin tags exist, the D/E
 * registries are filled on boot, and a workflow executes end-to-end against
 * a PSR-18 fixture client (the F1 vertical-slice proof).
 */
function protocolHttpOpenApiFixture(): string
{
    $json = json_encode([
        'openapi' => '3.0.0',
        'info' => ['title' => 'Pets', 'version' => '1.0.0'],
        'servers' => [['url' => 'http://api.example.com']],
        'paths' => [
            '/pets' => [
                'get' => [
                    'operationId' => 'listPets',
                    'responses' => ['200' => ['description' => 'OK']],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $file = tempnam(sys_get_temp_dir(), 'openapi_f13_').'.json';
    file_put_contents($file, $json);

    return $file;
}

function protocolHttpArazzoDocument(string $openApiFile, ?string $operationPath = null): ArazzoDocument
{
    $step = StepFactory::http(
        stepId: 's1',
        description: null,
        flow: new StepFlow(),
        io: new StepIo(
            successCriteria: [new SuccessCriterion(null, '$statusCode == 200', null)],
            outputs: ['petName' => new Expression('$.name')],
        ),
        operationId: $operationPath === null ? 'listPets' : null,
        operationPath: $operationPath,
    );

    $workflow = new Workflow(
        workflowId: 'wf-1',
        summary: null,
        description: null,
        inputs: null,
        dependsOn: [],
        steps: [$step],
        successActions: [],
        failureActions: [],
        outputs: ['petName' => new Expression('{$steps.s1.outputs.petName}')],
        parameters: [],
    );

    return new ArazzoDocument(
        arazzo: '1.0.1',
        info: new Info('Pets', null, null, '1.0.0'),
        sourceDescriptions: [new SourceDescription('pets-api', $openApiFile, SourceType::Openapi)],
        workflows: [$workflow],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

function protocolHttpFixtureClient(): ClientInterface
{
    return new class() implements ClientInterface
    {
        /** @var list<RequestInterface> */
        public array $requests = [];

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $this->requests[] = $request;

            return new Response(200, ['Content-Type' => 'application/json'], '{"name":"fido"}');
        }
    };
}

it('binds the protocol value seams to their protocol-http implementations', function (): void {
    expect(app(OpenApiExecutorInterface::class))->toBeInstanceOf(DefaultOpenApiExecutor::class)
        ->and(app(OutputExtractorInterface::class))->toBeInstanceOf(StepOutputExtractor::class)
        ->and(app(ResponseValidatorInterface::class))->toBeInstanceOf(ResponseSchemaValidator::class);
});

it('tags the protocol services under the configured plugin vocabulary', function (): void {
    $parentTag = config('arazzo.plugins.tags.0');
    $executorTag = config('arazzo.plugins.operation_executor');

    expect($parentTag)->toBe('arazzo.plugins.*')
        ->and($executorTag)->toBe('arazzo.plugins.operation-executor');

    $parent = iterator_to_array(app()->tagged($parentTag), false);
    $executors = iterator_to_array(app()->tagged($executorTag), false);

    $parentClasses = array_map(static fn (object $service): string => $service::class, $parent);

    expect($parentClasses)
        ->toContain(OpenApiSourceNormalizer::class)
        ->toContain(HttpStepExecutor::class)
        ->toContain(AsyncApiStepExecutor::class)
        ->toContain(ResponseSchemaValidator::class)
        ->and($executors)->toHaveCount(2)
        ->and($executors[0])->toBeInstanceOf(HttpStepExecutor::class)
        ->and($executors[1])->toBeInstanceOf(AsyncApiStepExecutor::class);
});

it('fills the protocol registries during provider boot', function (): void {
    $plugins = app(OperationExecutorRegistry::class)->all();

    expect($plugins)->toHaveCount(2)
        ->and($plugins[0])->toBeInstanceOf(HttpStepExecutor::class)
        ->and($plugins[1])->toBeInstanceOf(AsyncApiStepExecutor::class);

    expect(app(SourceNormalizerRegistryInterface::class)->get(SourceType::Openapi))
        ->toBeInstanceOf(OpenApiSourceNormalizer::class);

    $validatorsProperty = new ReflectionProperty(ResponseValidatorDispatcher::class, 'validators');
    $validators = $validatorsProperty->getValue(app(ResponseValidatorDispatcher::class));

    expect($validators)->toHaveCount(1)
        ->and($validators[0])->toBeInstanceOf(ResponseSchemaValidator::class);
});

it('validates an OpenAPI-backed fixture document through the container preflight gate', function (): void {
    $openApiFile = protocolHttpOpenApiFixture();

    // checkStep only resolves operations when the source is locally
    // registered — otherwise it degrades to a non-blocking warning.
    app(SourceRegistry::class)->register(new SourceDocument(
        'pets-api',
        SourceType::Openapi,
        $openApiFile,
        json_decode((string) file_get_contents($openApiFile), true, flags: JSON_THROW_ON_ERROR),
    ));

    $valid = app(PreflightValidator::class)->validate(
        protocolHttpArazzoDocument($openApiFile, '{$sourceDescriptions.pets-api.url}#/paths/~1pets/get'),
    );
    expect($valid->isValid())->toBeTrue();

    $missingOperation = app(PreflightValidator::class)->validate(
        protocolHttpArazzoDocument($openApiFile, '{$sourceDescriptions.pets-api.url}#/paths/~1missing/get'),
    );
    expect($missingOperation->isValid())->toBeFalse();
});

it('executes a workflow end-to-end against a PSR-18 fixture client (F1 slice)', function (): void {
    $client = protocolHttpFixtureClient();

    // The provider binds its value seams eagerly at boot, before the test
    // body can install the fixture transport — re-point them here.
    app()->instance(ClientInterface::class, $client);
    app()->instance(OpenApiExecutorInterface::class, new DefaultOpenApiExecutor(
        $client,
        app(RequestFactoryInterface::class),
    ));
    app()->instance(OutputExtractorInterface::class, new StepOutputExtractor(
        app(OpenApiOperationResolver::class),
        app(EvaluationEngineInterface::class),
        app(ExpressionEngineInterface::class),
    ));
    app()->instance(ResponseValidatorInterface::class, new ResponseSchemaValidator(
        app(OpenApiOperationResolver::class),
    ));

    $document = protocolHttpArazzoDocument(protocolHttpOpenApiFixture());

    $result = app(WorkflowExecutor::class)->execute($document->workflows[0], $document, []);

    expect($result->status)->toBe('succeeded')
        ->and($result->outputs)->toBe(['petName' => 'fido'])
        ->and($result->stepResults['s1']->success)->toBeTrue()
        ->and($client->requests)->toHaveCount(1)
        ->and((string) $client->requests[0]->getUri())->toContain('/pets');

    // The registrar-built plugin executes the same slice directly.
    app()->forgetInstance(ProtocolHttpRegistrar::class);
    $registrar = app(ProtocolHttpRegistrar::class);

    $registry = new OperationExecutorRegistry();
    foreach ($registrar->executors() as $plugin) {
        $registry->register($plugin);
    }

    $plugin = $registry->all()[0];
    expect($plugin)->toBeInstanceOf(HttpStepExecutor::class);

    $outcome = $plugin->execute(
        $document->workflows[0]->steps[0],
        new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-2'),
        $document,
        'exec-2',
    );

    expect($outcome->statusCode)->toBe(200)
        ->and($outcome->outputs)->toBe(['petName' => 'fido'])
        ->and($client->requests)->toHaveCount(2);
});
