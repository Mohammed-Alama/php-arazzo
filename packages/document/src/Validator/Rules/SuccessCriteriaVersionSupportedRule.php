<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * Guard the Expression Type Object `version` for `xpath` criteria against the
 * versions the Arazzo Specification recognises.
 *
 * The specification (Expression Type Object) permits `xpath-10`, `xpath-20`,
 * `xpath-30` and `xpath-31`, and defines `xpath-31` as the default when the
 * version is omitted. Any other value is not a recognised version. The
 * evaluation engine currently applies XPath 1.0 semantics (DOMXPath); higher
 * versions are accepted at validation time and evaluated on a best-effort
 * basis.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class SuccessCriteriaVersionSupportedRule implements Rule
{
    /** @var list<string> */
    private const XPATH_VERSIONS = ['xpath-10', 'xpath-20', 'xpath-30', 'xpath-31'];

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                foreach ($s->io->successCriteria as $k => $c) {
                    if ($c->type !== CriterionType::XPath || $c->version === null) {
                        continue;
                    }

                    if (!in_array($c->version, self::XPATH_VERSIONS, true)) {
                        $errors->error(
                            $this->code(),
                            "criterion type 'xpath' version '{$c->version}' is not a recognised XPath version (allowed: ".implode(', ', self::XPATH_VERSIONS).').',
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
