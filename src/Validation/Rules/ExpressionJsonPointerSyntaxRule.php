<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\RequestPart;
use Alama\LaravelArazzo\Expression\Ast\ResponsePart;
use Alama\LaravelArazzo\Expression\Ast\StepRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionJsonPointerSyntaxRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) {
                continue;
            }
            if (!$ast instanceof StepRef) {
                continue;
            }
            $part = $ast->part;
            if (!($part instanceof RequestPart) && !($part instanceof ResponsePart)) {
                continue;
            }
            $ptr = $part->jsonPointer;
            if ($ptr === null || $ptr === '') {
                continue;
            }

            $segments = explode('/', ltrim($ptr, '/'));
            foreach ($segments as $seg) {
                if (preg_match('/~(?![01])/', $seg) === 1) {
                    $errors->error($this->code(), "JSON Pointer '{$ptr}' contains illegal '~' escape.", $site->pointer);
                    break;
                }
            }
        }
    }

    public function code(): string
    {
        return 'expr.jsonpointer_syntax';
    }
}
