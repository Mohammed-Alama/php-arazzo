<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Document\Validator\OfficialSchemaRule;
use Alama\Arazzo\Document\Validator\RuleSet;
use Alama\Arazzo\Document\Validator\Validator;

class RecordingRule implements Rule
{
    public function code(): string
    {
        return 'r.a';
    }

    public function check(ArazzoDocument $d, SymbolTable $s, ErrorCollector $e): void
    {
        $e->error('r.a', 'msg', '/foo');
        $e->warning('r.a.warn', 'wmsg', '/bar');
    }
}

it('collects errors and warnings', function (): void {
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), []);
    $result = (new Validator(new RuleSet([new RecordingRule()])))->validate($doc);

    expect($result->isValid())->toBeFalse()
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]->code)->toBe('r.a')
        ->and($result->warnings)->toHaveCount(1);
});

it('rejects criterion with type as object per prose overrides', function (): void {
    $rawRoot = [
        'arazzo' => '1.0.0',
        'info' => ['title' => 'Test', 'version' => '1'],
        'sourceDescriptions' => [
            [
                'name' => 'test',
                'url' => 'https://example.com',
                'type' => 'openapi',
            ],
        ],
        'workflows' => [
            [
                'workflowId' => 'wf1',
                'summary' => 'Test workflow',
                'steps' => [
                    [
                        'stepId' => 's1',
                        'operationId' => 'op1',
                        'successCriteria' => [
                            [
                                'type' => ['kind' => 'body'],
                                'condition' => '$status == 200',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'components' => [],
    ];

    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), [], $rawRoot);
    $ec = new ErrorCollector();
    $validator = new Validator(
        new RuleSet([
            new OfficialSchemaRule(),
        ]),
    );
    $result = $validator->validate($doc);

    expect($result->isValid())->toBeFalse();

    $hasSchemaInvalid = false;
    foreach ($result->errors as $e) {
        if ($e->code === 'schema.invalid') {
            $hasSchemaInvalid = true;
            break;
        }
    }
    expect($hasSchemaInvalid)->toBeTrue();
});
