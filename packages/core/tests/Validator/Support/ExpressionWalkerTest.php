<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Support;

use Alama\Arazzo\Expression\Expression;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\ParameterIn;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Spec\PayloadReplacement;
use Alama\Arazzo\Spec\RequestBody;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\SuccessCriterion;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Validator\Support\ExpressionSite;
use Alama\Arazzo\Validator\Support\ExpressionWalker;

it('walks every expression context', function (): void {
    $body = new RequestBody(
        null,
        new Expression('{$inputs.x}'),
        [new PayloadReplacement('/t', new Expression('{$inputs.y}'))],
    );
    $crit = new SuccessCriterion('{$inputs.ctx}', '{$inputs.cond}', null);
    $step = new Step(
        's', null, 'op', null, null,
        [new Parameter('p', ParameterIn::Query, new Expression('{$inputs.p}'))],
        $body,
        [$crit],
        [], [],
        ['out' => new Expression('{$inputs.o}')],
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
    $step = new Step(
        's', null, 'op', null, null,
        [new Parameter('p', ParameterIn::Query, 'literal')],
        $body,
        [new SuccessCriterion('plain-context', 'plain-condition', null)],
        [], [], [],
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
