<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Interfaces\Rule;

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
