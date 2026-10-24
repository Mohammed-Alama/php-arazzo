<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * Guard the Expression Type Object `version` for `xpath` criteria.
 *
 * The specification recognises `xpath-10`, `xpath-20`, `xpath-30` and
 * `xpath-31`, and defines `xpath-31` as the default. This engine implements
 * XPath 1.0 only (DOMXPath), so a recognised version it cannot actually apply
 * is refused here rather than accepted by validation and then failed at
 * evaluation time. The supported set is injected from the bound evaluator
 * (`RuleSet::default()`), which keeps the validator and the runtime in step.
 *
 * A criterion that omits `version` runs as `xpath-10`: the deliberate,
 * documented deviation from the specification default, because 3.1 semantics
 * are not available.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class SuccessCriteriaVersionSupportedRule implements Rule
{
    /**
     * Versions the Expression Type Object recognises; anything else is not a
     * specification spelling at all.
     *
     * @var list<string>
     */
    private const SPEC_VERSIONS = ['xpath-10', 'xpath-20', 'xpath-30', 'xpath-31'];

    /**
     * @param  list<string>  $supportedVersions  Versions the bound evaluator can apply.
     */
    public function __construct(
        private readonly array $supportedVersions = ['xpath-10'],
    ) {}

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                foreach ($s->io->successCriteria as $k => $c) {
                    if ($c->type !== CriterionType::XPath || $c->version === null) {
                        continue;
                    }

                    $path = "/workflows/{$i}/steps/{$j}/successCriteria/{$k}/type/version";

                    if (!in_array($c->version, self::SPEC_VERSIONS, true)) {
                        $errors->error(
                            $this->code(),
                            "criterion type 'xpath' version '{$c->version}' is not a recognised XPath version (allowed: ".implode(', ', self::SPEC_VERSIONS).').',
                            $path,
                        );

                        continue;
                    }

                    if (!in_array($c->version, $this->supportedVersions, true)) {
                        $errors->error(
                            $this->code(),
                            "criterion type 'xpath' version '{$c->version}' is recognised by the specification but not implemented by this evaluator (supports: ".implode(', ', $this->supportedVersions).'). Pin a supported version, or omit the version to run as the engine default.',
                            $path,
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
