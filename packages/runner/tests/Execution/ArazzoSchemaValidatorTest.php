<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Contracts\Exceptions\SchemaValidationException;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Document\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Document\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Document\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Document\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Document\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Document\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Runner\Execution\ResponseSchemaValidator;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Schema;

it('validates a response against the OpenAPI schema', function (): void {
    // Setup a dummy OpenAPI operation with a schema
    $operation = new Operation([
        'responses' => [
            '200' => new Response([
                'description' => 'Success',
                'content' => [
                    'application/json' => [
                        'schema' => new Schema([
                            'type' => 'object',
                            'required' => ['id'],
                            'properties' => ['id' => ['type' => 'integer']],
                        ]),
                    ],
                ],
            ]),
        ],
    ]);

    $resolver = new DefaultSourceResolver([]);
    $loader = new OpenApiDocumentLoader($resolver);
    $opResolver = new OpenApiOperationResolver(
        $loader,
        new OpenApiVersionDetector(),
        new OpenApi30Normalizer(),
        new OpenApi31Normalizer(),
    );

    $validator = new class($opResolver) extends ResponseSchemaValidator
    {
        public ?Operation $mockOperation = null;

        public function __construct($sourceResolver)
        {
            parent::__construct($sourceResolver);
        }

        protected function findOperation(Step $step, ?ArazzoDocument $document = null): ?Operation
        {
            return $this->mockOperation;
        }
    };
    $validator->mockOperation = $operation;

    $step = new Step('test-step', null, 'operationId', null, null, [], null, [], [], [], []);

    // 1. Valid data -> no exception
    $validator->validateResponseSchema($step, 200, 'application/json', ['id' => 123]);
    expect(true)->toBeTrue(); // If we reached here, no exception was thrown

    // 2. Invalid data -> throws SchemaValidationException
    try {
        $validator->validateResponseSchema($step, 200, 'application/json', ['name' => 'wrong']);
        $this->fail('Expected SchemaValidationException');
    } catch (SchemaValidationException $e) {
        expect($e->stepId)->toBe('test-step')
            ->and($e->violations)->toHaveCount(1)
            ->and($e->getMessage())->toContain("missing required property 'id'");
    }

    // 3. Different status code -> no schema found -> no exception (ignores)
    $validator->validateResponseSchema($step, 201, 'application/json', ['name' => 'wrong']);

    // 4. Different content type -> no schema found -> no exception (ignores)
    $validator->validateResponseSchema($step, 200, 'application/xml', ['name' => 'wrong']);
});
