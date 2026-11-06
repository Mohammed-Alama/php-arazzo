<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\Enum\ReferenceKind;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StepOperationPathSyntaxRule implements Rule
{
    public function __construct(private readonly ExpressionEngineInterface $engine) {}

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->target->operationPath === null) {
                    continue;
                }
                $path = "/workflows/{$i}/steps/{$j}/operationPath";
                if (!str_contains($s->target->operationPath, '#')) {
                    $errors->error($this->code(), "operationPath '{$s->target->operationPath}' must contain '#' separating source and JSON Pointer.", $path);

                    continue;
                }
                [$src, $ptr] = explode('#', $s->target->operationPath, 2);
                $sourceName = $this->sourceName($src, $doc->specVersion);

                if ($sourceName === null) {
                    $errors->error($this->code(), "operationPath source '{$src}' must be the expression '{{\$sourceDescriptions.NAME.url}}'.", $path);

                    continue;
                }

                if (!isset($symbols->sourceDescriptions[$sourceName])) {
                    $errors->error($this->code(), "operationPath source '{$sourceName}' is not a declared sourceDescription.", $path);
                }
                if ($ptr === '' || $ptr[0] !== '/') {
                    $errors->error($this->code(), "operationPath JSON Pointer '{$ptr}' must start with '/'.", $path);
                }
            }
        }
    }

    private function sourceName(string $source, SpecVersion $version): ?string
    {
        try {
            $segments = $this->engine->parseEmbedded($source, $version);
        } catch (ExpressionSyntaxException) {
            return null;
        }

        // The source part must be exactly one embedded expression, not a
        // template that merely contains one.
        if (count($segments) !== 1) {
            return null;
        }

        $segment = $segments[0];
        if (!$segment->isExpression
            || $segment->offset !== 0
            || strlen($segment->text) + 2 !== strlen($source)) {
            return null;
        }

        $reference = $this->engine->expressionReferences($segment->text);

        return $reference !== null
            && $reference->kind === ReferenceKind::Source
            && $reference->name === 'url'
            ? $reference->target
            : null;
    }

    public function code(): string
    {
        return 'step.operationpath_syntax';
    }
}
