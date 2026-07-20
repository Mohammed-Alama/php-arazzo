<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class SuccessCriteriaVersionSupportedRule implements Rule
{
    /** @var array<string, list<string>> */
    private const UNSUPPORTED = [
        'xpath' => ['xpath-30', 'xpath-31'],
    ];

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                foreach ($s->successCriteria as $k => $c) {
                    if ($c->type !== CriterionType::XPath || $c->version === null) {
                        continue;
                    }

                    if (in_array($c->version, self::UNSUPPORTED['xpath'], true)) {
                        $errors->error(
                            $this->code(),
                            "criterion type 'xpath' version '{$c->version}' is not supported — DOMXPath only implements XPath 1.0 (use 'xpath-10' or omit version).",
                            "/workflows/{$i}/steps/{$j}/successCriteria/{$k}/type/version",
                        );
                    }
                }
            }
        }
    }

    public function code(): string
    {
        return 'step.success_criteria_version_supported';
    }
}
