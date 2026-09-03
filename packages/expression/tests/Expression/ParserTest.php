<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression;

use Alama\Arazzo\Expression\Ast\ComponentRef;
use Alama\Arazzo\Expression\Ast\HttpMetaRef;
use Alama\Arazzo\Expression\Ast\InputRef;
use Alama\Arazzo\Expression\Ast\MessageRef;
use Alama\Arazzo\Expression\Ast\OutputPart;
use Alama\Arazzo\Expression\Ast\OutputRef;
use Alama\Arazzo\Expression\Ast\RequestPart;
use Alama\Arazzo\Expression\Ast\ResponsePart;
use Alama\Arazzo\Expression\Ast\SelfRef;
use Alama\Arazzo\Expression\Ast\SourceRef;
use Alama\Arazzo\Expression\Ast\StepRef;
use Alama\Arazzo\Expression\Ast\WorkflowRef;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Parser;
use Alama\Arazzo\Expression\Parser as ExprParser;

it('parses $inputs.name', function (): void {
    $ast = (new ExprParser())->parse('{$inputs.userId}');
    expect($ast)->toBeInstanceOf(InputRef::class)
        ->and($ast->name)->toBe('userId');
});

it('parses $steps.s.outputs.o', function (): void {
    $ast = (new ExprParser())->parse('{$steps.fetch.outputs.user}');
    expect($ast)->toBeInstanceOf(StepRef::class)
        ->and($ast->stepId)->toBe('fetch')
        ->and($ast->part)->toBeInstanceOf(OutputPart::class)
        ->and($ast->part->name)->toBe('user');
});

it('parses $steps.s.response.body#/x/0', function (): void {
    $ast = (new ExprParser())->parse('{$steps.s.response.body#/x/0}');
    expect($ast->part)->toBeInstanceOf(ResponsePart::class)
        ->and($ast->part->httpPart)->toBe('body')
        ->and($ast->part->jsonPointer)->toBe('/x/0');
});

it('parses $workflows.w.outputs.o', function (): void {
    $ast = (new ExprParser())->parse('{$workflows.main.outputs.token}');
    expect($ast)->toBeInstanceOf(WorkflowRef::class)
        ->and($ast->workflowId)->toBe('main')
        ->and($ast->partKind)->toBe('outputs')
        ->and($ast->name)->toBe('token');
});

it('parses $sourceDescriptions.api with subpath', function (): void {
    $ast = (new ExprParser())->parse('{$sourceDescriptions.api.workflows.x}');
    expect($ast)->toBeInstanceOf(SourceRef::class)
        ->and($ast->name)->toBe('api')
        ->and($ast->subPath)->toBe('workflows.x');
});

it('parses $components.parameters.name', function (): void {
    $ast = (new ExprParser())->parse('{$components.parameters.Trace}');
    expect($ast)->toBeInstanceOf(ComponentRef::class)
        ->and($ast->type)->toBe('parameters')
        ->and($ast->name)->toBe('Trace');
});

it('parses $statusCode', function (): void {
    $ast = (new ExprParser())->parse('{$statusCode}');
    expect($ast)->toBeInstanceOf(HttpMetaRef::class)
        ->and($ast->field)->toBe('statusCode');
});

it('rejects unknown root token', function (): void {
    (new ExprParser())->parse('{$foobar}');
})->throws(ExpressionSyntaxException::class);

it('parses $request.query.name', function () {
    $ast = (new Parser())->parse('$request.query.page');
    expect($ast)->toBeInstanceOf(StepRef::class)
        ->and($ast->stepId)->toBeNull()
        ->and($ast->part)->toBeInstanceOf(RequestPart::class)
        ->and($ast->part->httpPart)->toBe('query')
        ->and($ast->part->headerName)->toBe('page');
});

it('parses $request.path.id', function () {
    $ast = (new Parser())->parse('$request.path.id');
    expect($ast)->toBeInstanceOf(StepRef::class)
        ->and($ast->part)->toBeInstanceOf(RequestPart::class)
        ->and($ast->part->httpPart)->toBe('path')
        ->and($ast->part->headerName)->toBe('id');
});

it('parses $steps.s.request.query.name and $steps.s.request.path.id', function () {
    $parser = new Parser();

    $query = $parser->parse('$steps.s.request.query.page');
    expect($query)->toBeInstanceOf(StepRef::class)
        ->and($query->part)->toBeInstanceOf(RequestPart::class)
        ->and($query->part->httpPart)->toBe('query')
        ->and($query->part->headerName)->toBe('page');

    $path = $parser->parse('$steps.s.request.path.id');
    expect($path->part->httpPart)->toBe('path')
        ->and($path->part->headerName)->toBe('id');
});

it('parses $inputs.name#/pointer (1.1)', function () {
    $ast = (new Parser())->parse('$inputs.user#/address/city');
    expect($ast)->toBeInstanceOf(InputRef::class)
        ->and($ast->name)->toBe('user')
        ->and($ast->jsonPointer)->toBe('/address/city');
});

it('parses $outputs.name#/pointer (1.1)', function () {
    $ast = (new Parser())->parse('$outputs.token');
    expect($ast)->toBeInstanceOf(OutputRef::class)
        ->and($ast->jsonPointer)->toBeNull();

    $withPtr = (new Parser())->parse('$outputs.token#/claims/sub');
    expect($withPtr->jsonPointer)->toBe('/claims/sub');
});

it('parses $steps.s.outputs.o#/pointer (1.1)', function () {
    $ast = (new Parser())->parse('$steps.fetch.outputs.user#/profile/email');
    expect($ast)->toBeInstanceOf(StepRef::class)
        ->and($ast->part)->toBeInstanceOf(OutputPart::class)
        ->and($ast->part->jsonPointer)->toBe('/profile/email');
});

it('parses $message.header.Name and $message.payload#/ptr (1.1)', function () {
    $parser = new Parser();

    $header = $parser->parse('$message.header.X-Trace');
    expect($header)->toBeInstanceOf(MessageRef::class)
        ->and($header->part)->toBe('header')
        ->and($header->name)->toBe('X-Trace');

    $payload = $parser->parse('$message.payload#/order/id');
    expect($payload)->toBeInstanceOf(MessageRef::class)
        ->and($payload->part)->toBe('payload')
        ->and($payload->jsonPointer)->toBe('/order/id');
});

it('parses bare $self (1.1)', function () {
    expect((new Parser())->parse('$self'))->toBeInstanceOf(SelfRef::class);
});

it('rejects malformed message and self forms', function (string $raw): void {
    (new Parser())->parse($raw);
})->throws(ExpressionSyntaxException::class)->with([
    '$message',
    '$message.payload.extra',
    '$self.url',
]);
