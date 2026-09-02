<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\Ast\RequestPart;
use Alama\Arazzo\Expression\Ast\ResponsePart;
use Alama\Arazzo\Expression\Ast\StepRef;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Parser as ExpressionParser;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Interfaces\Rule;
use Alama\Arazzo\Validator\Support\ExpressionWalker;

final class ExpressionJsonPointerSyntaxRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = (new ExpressionParser())->parseOrError($site->expression->raw);
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
