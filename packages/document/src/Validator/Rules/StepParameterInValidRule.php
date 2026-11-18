<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StepParameterInValidRule implements Rule
{
    /** @var list<ParameterIn> */
    private const V1_0_IN = [ParameterIn::Path, ParameterIn::Query, ParameterIn::Header, ParameterIn::Cookie];

    /** @var list<ParameterIn> */
    private const V1_1_IN = [
        ParameterIn::Path,
        ParameterIn::Query,
        ParameterIn::Header,
        ParameterIn::Cookie,
        ParameterIn::Body,
        ParameterIn::Querystring,
        ParameterIn::Metadata,
        ParameterIn::Variable,
    ];

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $w) {
            foreach ($w->steps as $si => $s) {
                foreach ($s->io->parameters as $pi => $p) {
                    $this->checkParameter($p, $doc, $errors, "/workflows/{$wi}/steps/{$si}/parameters/{$pi}/in");
                }
            }
        }

        foreach ($doc->components->parameters as $name => $p) {
            $this->checkParameter($p, $doc, $errors, "/components/parameters/{$name}/in");
        }
    }

    private function checkParameter(Parameter|Reusable $p, ArazzoDocument $doc, ErrorCollector $errors, string $path): void
    {
        if ($p instanceof Reusable || $p->in === null) {
            return;
        }

        $allowed = $doc->specVersion === SpecVersion::V1_0 ? self::V1_0_IN : self::V1_1_IN;
        if (in_array($p->in, $allowed, true)) {
            return;
        }

        $message = $p->in === ParameterIn::Querystring
            ? "Parameter 'in' value 'querystring' requires Arazzo 1.1.0."
            : "Parameter 'in' value '{$p->in->value}' is not allowed in Arazzo {$doc->arazzo}.";

        $errors->error($this->code(), $message, $path);
    }

    public function code(): string
    {
        return 'step.parameter_in_valid';
    }
}
