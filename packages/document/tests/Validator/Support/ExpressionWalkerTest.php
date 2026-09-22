<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Support;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\PayloadReplacement;
use Alama\Arazzo\Contracts\Spec\RequestBody;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\Support\ExpressionSite;
use Alama\Arazzo\Document\Validator\Support\ExpressionWalker;
use Alama\Arazzo\Expression\SymbolTable;

it('walks every expression context', function (): void {
    $body = new RequestBody(
        null,
        new Expression('{$inputs.x}'),
        [new PayloadReplacement('/t', new Expression('{$inputs.y}'))],
    );
    $crit = new SuccessCriterion('{$inputs.ctx}', '{$inputs.cond}', null);
    $step = StepFactory::http(
        's', null,
        new StepFlow(),
        new StepIo(
            parameters: [new Parameter('p', ParameterIn::Query, new Expression('{$inputs.p}'))],
            requestBody: $body,
            successCriteria: [$crit],
            outputs: ['out' => new Expression('{$inputs.o}')],
        ),
        operationId: 'op',
    );
    $wf = new Workflow(
        'w', null, null,
        ['type' => 'object', 'properties' => ['p' => [], 'x' => [], 'y' => [], 'o' => [], 'ctx' => [], 'cond' => []]],
        [],
        [$step],
        [], [],
        ['wo' => new Expression('{$inputs.p}')],
        [new Parameter('wp', ParameterIn::Query, new Expression('{$inputs.p}'))],
    );
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$wf], new Components([], [], [], []), []);

    $contexts = [];
    foreach ((new ExpressionWalker())->walk($doc, SymbolTable::build($doc)) as $site) {
        expect($site)->toBeInstanceOf(ExpressionSite::class);
        $contexts[] = $site->context;
    }
    expect($contexts)->toContain('wf.parameters')
        ->toContain('wf.outputs')
        ->toContain('parameters')
        ->toContain('requestBody')
        ->toContain('criteria')
        ->toContain('outputs');
});

it('skips non-Expression values', function (): void {
    $body = new RequestBody(null, 'literal', [new PayloadReplacement('/t', 'literal')]);
    $step = StepFactory::http(
        's', null,
        new StepFlow(),
        new StepIo(
            parameters: [new Parameter('p', ParameterIn::Query, 'literal')],
            requestBody: $body,
            successCriteria: [new SuccessCriterion('plain-context', 'plain-condition', null)],
        ),
        operationId: 'op',
    );
    $wf = new Workflow(
        'w', null, null, null, [], [$step], [], [], [],
        [new Parameter('wp', ParameterIn::Query, 'literal')],
    );
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$wf], new Components([], [], [], []), []);
    $count = 0;
    foreach ((new ExpressionWalker())->walk($doc, SymbolTable::build($doc)) as $_) {
        $count++;
    }
    expect($count)->toBe(0);
});
