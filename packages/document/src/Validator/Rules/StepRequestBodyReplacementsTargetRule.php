<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

final class StepRequestBodyReplacementsTargetRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->requestBody === null) {
                    continue;
                }
                foreach ($s->requestBody->replacements as $k => $r) {
                    if ($r->target === '' || $r->target[0] !== '/') {
                        $errors->error(
                            $this->code(),
                            "PayloadReplacement target '{$r->target}' must be a JSON Pointer starting with '/'.",
                            "/workflows/{$i}/steps/{$j}/requestBody/replacements/{$k}/target",
                        );
                    }
                }
            }
        }
    }

    public function code(): string
    {
        return 'step.request_body_replacements_target';
    }
}
