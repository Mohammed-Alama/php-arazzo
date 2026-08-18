<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Expression\Ast\HttpMetaRef;
use Alama\Arazzo\Expression\Ast\RequestPart;
use Alama\Arazzo\Expression\Ast\ResponsePart;
use Alama\Arazzo\Expression\Ast\StepRef;
use Alama\Arazzo\Expression\ExpressionSyntaxException;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;
use Alama\Arazzo\Validator\Support\ExpressionWalker;

final class ExpressionContextMisuseRule implements Rule
{
    private const ALLOWED = ['criteria', 'outputs', 'onSuccess', 'onFailure'];

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) {
                continue;
            }

            $isRuntime = $ast instanceof HttpMetaRef
                || ($ast instanceof StepRef && ($ast->part instanceof RequestPart || $ast->part instanceof ResponsePart));

            if ($isRuntime && !in_array($site->context, self::ALLOWED, true)) {
                $errors->error(
                    $this->code(),
                    "Runtime reference (\$response/\$request/\$statusCode/\$url/\$method) is not valid in context '{$site->context}'.",
                    $site->pointer,
                );
            }
        }
    }

    public function code(): string
    {
        return 'expr.context_misuse';
    }
}
