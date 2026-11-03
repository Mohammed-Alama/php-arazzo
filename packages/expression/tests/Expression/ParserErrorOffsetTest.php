<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression;

use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Parser;

it('pins the syntax error message and byte offset for representative failures', function (string $raw, string $message, int $offset): void {
    $thrown = false;

    try {
        (new Parser())->parse($raw);
    } catch (ExpressionSyntaxException $e) {
        $thrown = true;

        expect($e->getMessage())->toBe($message)
            ->and($e->offset)->toBe($offset)
            ->and($e->expression)->toBe($raw);
    }

    expect($thrown)->toBeTrue();
})->with([
    'unknown root' => ['$foobar', 'Expression must start with a keyword: $foobar', 1],
    'simple ref missing name' => ['$inputs.', 'Malformed reference: $inputs.', 8],
    'step ref missing step id' => ['$steps.', 'Malformed step reference: $steps.', 7],
    'step outputs missing name' => ['$steps.fetch.outputs.', 'Malformed reference: $steps.fetch.outputs.', 21],
    'self with suffix' => ['$self.url', 'Malformed $self reference: $self.url', 5],
    'message missing part' => ['$message', 'Malformed message reference: $message', 8],
    'message unknown part' => ['$message.body', 'Message part must be header or payload in: $message.body', 9],
    'http part missing keyword' => ['$request.', "Expected keyword after '.' in: \$request.", 9],
    'workflow missing parts' => ['$workflows.main', 'Malformed workflow reference: $workflows.main', 15],
    'components missing type' => ['$components.', 'Malformed components reference: $components.', 12],
    'header missing name' => ['$steps.fetch.request.query', "Expected '.name' after header in: \$steps.fetch.request.query", 26],
    'json pointer missing slash' => ['$message.payload#/order.id', "Expected '/' in JSON pointer at token 3 in: \$message.payload#/order.id", 23],
    'http meta with suffix' => ['$url.x', 'Malformed HTTP meta reference: $url.x', 4],
]);
