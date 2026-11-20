<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression;

use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Expression\Data\EvaluationContext;
use Alama\Arazzo\Expression\Internal\StepReferenceResolver;
use Alama\Arazzo\Expression\Internal\ValueReader;
use Alama\Arazzo\Expression\JsonPointer;
use Alama\Arazzo\Expression\Parser;

it('resolves array index', function (): void {
    $parser = new Parser();
    $ast = $parser->parse('{$steps.getPetsByStatus.outputs.availablePets[0].id}');

    $context = new WorkflowContext(
        definitionId: 'test',
        steps: [
            'getPetsByStatus' => [
                'outputs' => [
                    'availablePets' => [
                        ['id' => 1, 'name' => 'Buddy'],
                        ['id' => 2, 'name' => 'Max'],
                    ],
                ],
            ],
        ],
    );

    $resolver = new StepReferenceResolver(new ValueReader(new JsonPointer()));
    $evaluation = new EvaluationContext($context, currentStepId: 'getPetsByStatus');

    expect($resolver->resolve($ast, $evaluation))->toBe(1);
});
