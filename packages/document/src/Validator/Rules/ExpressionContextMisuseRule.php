<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Document\Validator\Support\ExpressionWalker;
use Alama\Arazzo\Expression\Enum\ReferenceKind;
use Alama\Arazzo\Expression\ExpressionEngineInterface;
use Alama\Arazzo\Expression\SymbolTable;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ExpressionContextMisuseRule implements Rule
{
    private const ALLOWED = ['criteria', 'outputs', 'onSuccess', 'onFailure'];

    public function __construct(private readonly ExpressionEngineInterface $engine) {}

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ref = $this->engine->expressionReferences($site->expression->raw);
            if ($ref === null) {
                continue;
            }

            $isRuntime = $ref->kind === ReferenceKind::HttpMeta
                || ($ref->kind === ReferenceKind::Step
                    && ($ref->part === 'request' || $ref->part === 'response'));

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
