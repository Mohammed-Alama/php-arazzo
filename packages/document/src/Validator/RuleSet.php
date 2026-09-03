<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator;

use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Document\Validator\Rules\ActionGotoTargetResolvesRule;
use Alama\Arazzo\Document\Validator\Rules\ActionRetryLimitsRule;
use Alama\Arazzo\Document\Validator\Rules\ActionReusableRefResolvesRule;
use Alama\Arazzo\Document\Validator\Rules\ActionTypeValidRule;
use Alama\Arazzo\Document\Validator\Rules\AsyncApiFieldsRequire11Rule;
use Alama\Arazzo\Document\Validator\Rules\ComponentsUniqueNamesRule;
use Alama\Arazzo\Document\Validator\Rules\DocumentArazzoVersionRule;
use Alama\Arazzo\Document\Validator\Rules\DocumentInfoRequiredRule;
use Alama\Arazzo\Document\Validator\Rules\DocumentSourceDescriptionsPresentRule;
use Alama\Arazzo\Document\Validator\Rules\DocUnknownFieldRule;
use Alama\Arazzo\Document\Validator\Rules\ExpressionContextMisuseRule;
use Alama\Arazzo\Document\Validator\Rules\ExpressionJsonPointerSyntaxRule;
use Alama\Arazzo\Document\Validator\Rules\ExpressionSyntaxRule;
use Alama\Arazzo\Document\Validator\Rules\ExpressionUnresolvedComponentRefRule;
use Alama\Arazzo\Document\Validator\Rules\ExpressionUnresolvedInputRefRule;
use Alama\Arazzo\Document\Validator\Rules\ExpressionUnresolvedSourceRefRule;
use Alama\Arazzo\Document\Validator\Rules\ExpressionUnresolvedStepRefRule;
use Alama\Arazzo\Document\Validator\Rules\ExpressionUnresolvedWorkflowRefRule;
use Alama\Arazzo\Document\Validator\Rules\ExtensionsXPrefixRule;
use Alama\Arazzo\Document\Validator\Rules\ParameterQuerystringOperationShapeRule;
use Alama\Arazzo\Document\Validator\Rules\SelectorTypeSupportedRule;
use Alama\Arazzo\Document\Validator\Rules\SelfUriSyntaxRule;
use Alama\Arazzo\Document\Validator\Rules\SourceTypeMatchesRule;
use Alama\Arazzo\Document\Validator\Rules\SourceUniqueNameRule;
use Alama\Arazzo\Document\Validator\Rules\SourceUrlSyntaxRule;
use Alama\Arazzo\Document\Validator\Rules\StepAtLeastOneRule;
use Alama\Arazzo\Document\Validator\Rules\StepCriteriaTypeContextRule;
use Alama\Arazzo\Document\Validator\Rules\StepDependsOnNoCycleRule;
use Alama\Arazzo\Document\Validator\Rules\StepIdPatternRule;
use Alama\Arazzo\Document\Validator\Rules\StepNestedWorkflowExistsRule;
use Alama\Arazzo\Document\Validator\Rules\StepNestedWorkflowNoCycleRule;
use Alama\Arazzo\Document\Validator\Rules\StepOperationIdSourceScopedRule;
use Alama\Arazzo\Document\Validator\Rules\StepOperationPathSyntaxRule;
use Alama\Arazzo\Document\Validator\Rules\StepOperationTargetPresentRule;
use Alama\Arazzo\Document\Validator\Rules\StepOutputsUniqueRule;
use Alama\Arazzo\Document\Validator\Rules\StepParameterInValidRule;
use Alama\Arazzo\Document\Validator\Rules\StepParametersHaveNameRule;
use Alama\Arazzo\Document\Validator\Rules\StepRequestBodyReplacementsTargetRule;
use Alama\Arazzo\Document\Validator\Rules\StepSuccessCriteriaConditionRule;
use Alama\Arazzo\Document\Validator\Rules\StepTimeoutRequires11Rule;
use Alama\Arazzo\Document\Validator\Rules\StepUniqueIdRule;
use Alama\Arazzo\Document\Validator\Rules\SubWorkflowInvokeTargetResolvesRule;
use Alama\Arazzo\Document\Validator\Rules\SuccessCriteriaVersionSupportedRule;
use Alama\Arazzo\Document\Validator\Rules\WorkflowAtLeastOneRule;
use Alama\Arazzo\Document\Validator\Rules\WorkflowDependsOnExistsRule;
use Alama\Arazzo\Document\Validator\Rules\WorkflowDependsOnNoCycleRule;
use Alama\Arazzo\Document\Validator\Rules\WorkflowIdPatternRule;
use Alama\Arazzo\Document\Validator\Rules\WorkflowInputsValidSchemaRule;
use Alama\Arazzo\Document\Validator\Rules\WorkflowUniqueIdRule;

final readonly class RuleSet
{
    /**
     * @param  list<Rule>  $rules
     * @param  list<string>  $disabled
     */
    public function __construct(
        public array $rules,
        public array $disabled = [],
        public bool $strict = true,
    ) {}

    /**
     * @param  list<string>  $disabled
     */
    public static function default(array $disabled = [], bool $strict = true): self
    {
        return new self([
            new OfficialSchemaRule(),
            new ActionGotoTargetResolvesRule(),
            new ActionRetryLimitsRule(),
            new ActionReusableRefResolvesRule(),
            new ActionTypeValidRule(),
            new AsyncApiFieldsRequire11Rule(),
            new ComponentsUniqueNamesRule(),
            new DocUnknownFieldRule(),
            new DocumentArazzoVersionRule(),
            new DocumentSourceDescriptionsPresentRule(),
            new DocumentInfoRequiredRule(),
            new ExpressionContextMisuseRule(),
            new ExpressionJsonPointerSyntaxRule(),
            new ExpressionSyntaxRule(),
            new ExpressionUnresolvedComponentRefRule(),
            new ExpressionUnresolvedInputRefRule(),
            new ExpressionUnresolvedSourceRefRule(),
            new ExpressionUnresolvedStepRefRule(),
            new ExpressionUnresolvedWorkflowRefRule(),
            new ExtensionsXPrefixRule(),
            new ParameterQuerystringOperationShapeRule(),
            new SelectorTypeSupportedRule(),
            new SelfUriSyntaxRule(),
            new SourceTypeMatchesRule(),
            new SourceUniqueNameRule(),
            new SourceUrlSyntaxRule(),
            new StepAtLeastOneRule(),
            new StepCriteriaTypeContextRule(),
            new StepDependsOnNoCycleRule(),
            new StepIdPatternRule(),
            new StepNestedWorkflowExistsRule(),
            new StepNestedWorkflowNoCycleRule(),
            new StepOperationIdSourceScopedRule(),
            new StepOperationPathSyntaxRule(),
            new StepOperationTargetPresentRule(),
            new StepTimeoutRequires11Rule(),
            new StepOutputsUniqueRule(),
            new StepParameterInValidRule(),
            new StepParametersHaveNameRule(),
            new StepRequestBodyReplacementsTargetRule(),
            new StepSuccessCriteriaConditionRule(),
            new StepUniqueIdRule(),
            new SubWorkflowInvokeTargetResolvesRule(),
            new SuccessCriteriaVersionSupportedRule(),
            new WorkflowAtLeastOneRule(),
            new WorkflowDependsOnExistsRule(),
            new WorkflowDependsOnNoCycleRule(),
            new WorkflowIdPatternRule(),
            new WorkflowInputsValidSchemaRule(),
            new WorkflowUniqueIdRule(),
        ], $disabled, $strict);
    }

    public function withRule(Rule $rule): self
    {
        return new self([...$this->rules, $rule], $this->disabled, $this->strict);
    }

    /** @return list<Rule> */
    public function rules(): array
    {
        return $this->rules;
    }

    /** @return list<Rule> */
    public function activeRules(): array
    {
        return array_values(array_filter(
            $this->rules,
            fn (Rule $r) => !in_array($r->code(), $this->disabled, true),
        ));
    }

    public function isStrict(): bool
    {
        return $this->strict;
    }
}
