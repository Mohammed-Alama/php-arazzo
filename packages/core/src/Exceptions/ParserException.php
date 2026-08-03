<?php

declare(strict_types=1);

namespace Alama\Arazzo\Exceptions;

use Alama\LaravelArazzo\Parser\ParseContext;

final class ParserException extends ArazzoException
{
    public static function missingField(ParseContext $ctx, string $field): self
    {
        $pointer = $ctx->push($field)->pointer();

        return new self("Missing required field: {$pointer}", $pointer, 'parser.missing_field');
    }

    public static function wrongType(ParseContext $ctx, string $expected, mixed $actual): self
    {
        $type = get_debug_type($actual);
        $pointer = $ctx->pointer();

        return new self("Expected {$expected} at {$pointer}, got {$type}", $pointer, 'parser.wrong_type');
    }

    public static function invalidEnum(ParseContext $ctx, string $expected, string $actual): self
    {
        $pointer = $ctx->pointer();

        return new self("Invalid value '{$actual}' at {$pointer}; expected one of {$expected}", $pointer, 'parser.invalid_enum');
    }

    public static function invalidActionType(ParseContext $ctx, string $actual): self
    {
        $pointer = $ctx->pointer();

        return new self("Invalid action type '{$actual}' at {$pointer}", $pointer, 'parser.invalid_action_type');
    }

    public static function unsupportedVersion(ParseContext $ctx, string $actual): self
    {
        $pointer = $ctx->push('arazzo')->pointer();

        return new self(
            "Unsupported arazzo version '{$actual}' at {$pointer}; only 1.0.x and 1.1.x are supported.",
            $pointer,
            'parser.unsupported_version',
        );
    }
}
