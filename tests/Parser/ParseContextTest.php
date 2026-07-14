<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Parser;

use Alama\LaravelArazzo\Parser\ParseContext;

it('builds JSON Pointer from segments', function (): void {
    $ctx = new ParseContext('/tmp/x.yaml');
    $sub = $ctx->push('workflows')->push(0)->push('steps')->push(2)->push('stepId');

    expect($sub->pointer())->toBe('/workflows/0/steps/2/stepId')
        ->and($sub->path())->toBe('/tmp/x.yaml');
});

it('escapes ~ and / per RFC 6901', function (): void {
    $ctx = (new ParseContext('/x'))->push('a/b')->push('c~d');
    expect($ctx->pointer())->toBe('/a~1b/c~0d');
});

it('root pointer is empty string', function (): void {
    expect((new ParseContext('/x'))->pointer())->toBe('');
});
