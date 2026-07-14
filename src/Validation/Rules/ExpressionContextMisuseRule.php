<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\HttpMetaRef;
use Alama\LaravelArazzo\Expression\Ast\RequestPart;
use Alama\LaravelArazzo\Expression\Ast\ResponsePart;
use Alama\LaravelArazzo\Expression\Ast\StepRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

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
