<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator;

use Alama\Arazzo\Validator\Rules\ActionGotoTargetResolvesRule;
use Alama\Arazzo\Validator\Rules\ActionRetryLimitsRule;
use Alama\Arazzo\Validator\Rules\ActionReusableRefResolvesRule;
use Alama\Arazzo\Validator\Rules\ActionTypeValidRule;
use Alama\Arazzo\Validator\Rules\AsyncApiFieldsRequire11Rule;
use Alama\Arazzo\Validator\Rules\ComponentsUniqueNamesRule;
use Alama\Arazzo\Validator\Rules\DocumentArazzoVersionRule;
use Alama\Arazzo\Validator\Rules\DocumentInfoRequiredRule;
use Alama\Arazzo\Validator\Rules\DocUnknownFieldRule;
use Alama\Arazzo\Validator\Rules\ExpressionContextMisuseRule;
use Alama\Arazzo\Validator\Rules\ExpressionJsonPointerSyntaxRule;
use Alama\Arazzo\Validator\Rules\ExpressionSyntaxRule;
use Alama\Arazzo\Validator\Rules\ExpressionUnresolvedComponentRefRule;
use Alama\Arazzo\Validator\Rules\ExpressionUnresolvedInputRefRule;
use Alama\Arazzo\Validator\Rules\ExpressionUnresolvedSourceRefRule;
use Alama\Arazzo\Validator\Rules\ExpressionUnresolvedStepRefRule;
use Alama\Arazzo\Validator\Rules\ExpressionUnresolvedWorkflowRefRule;
use Alama\Arazzo\Validator\Rules\ExtensionsXPrefixRule;
use Alama\Arazzo\Validator\Rules\ParameterQuerystringOperationShapeRule;
use Alama\Arazzo\Validator\Rules\SelectorTypeSupportedRule;
use Alama\Arazzo\Validator\Rules\SelfUriSyntaxRule;
use Alama\Arazzo\Validator\Rules\SourceTypeMatchesRule;
use Alama\Arazzo\Validator\Rules\SourceUniqueNameRule;
use Alama\Arazzo\Validator\Rules\SourceUrlSyntaxRule;
use Alama\Arazzo\Validator\Rules\StepAtLeastOneRule;
use Alama\Arazzo\Validator\Rules\StepCriteriaTypeContextRule;
use Alama\Arazzo\Validator\Rules\StepDependsOnNoCycleRule;
use Alama\Arazzo\Validator\Rules\StepIdPatternRule;
use Alama\Arazzo\Validator\Rules\StepNestedWorkflowExistsRule;
use Alama\Arazzo\Validator\Rules\StepNestedWorkflowNoCycleRule;
use Alama\Arazzo\Validator\Rules\StepOperationIdSourceScopedRule;
use Alama\Arazzo\Validator\Rules\StepOperationPathSyntaxRule;
use Alama\Arazzo\Validator\Rules\StepOperationTargetPresentRule;
use Alama\Arazzo\Validator\Rules\StepOutputsUniqueRule;
use Alama\Arazzo\Validator\Rules\StepParameterInValidRule;
use Alama\Arazzo\Validator\Rules\StepParametersHaveNameRule;
use Alama\Arazzo\Validator\Rules\StepRequestBodyReplacementsTargetRule;
use Alama\Arazzo\Validator\Rules\StepSuccessCriteriaConditionRule;
use Alama\Arazzo\Validator\Rules\StepUniqueIdRule;
use Alama\Arazzo\Validator\Rules\SubWorkflowInvokeTargetResolvesRule;
use Alama\Arazzo\Validator\Rules\SuccessCriteriaVersionSupportedRule;
use Alama\Arazzo\Validator\Rules\WorkflowAtLeastOneRule;
use Alama\Arazzo\Validator\Rules\WorkflowDependsOnExistsRule;
use Alama\Arazzo\Validator\Rules\WorkflowDependsOnNoCycleRule;
use Alama\Arazzo\Validator\Rules\WorkflowIdPatternRule;
use Alama\Arazzo\Validator\Rules\WorkflowInputsValidSchemaRule;
use Alama\Arazzo\Validator\Rules\WorkflowUniqueIdRule;

final readonly class RuleSet
{
    /**
     * @param list<Rule> $rules
     * @param list<string> $disabled
     */
    public function __construct(
        public array $rules,
        public array $disabled = [],
        public bool $strict = true,
    ) {
    }

    /**
     * @param list<string> $disabled
     */
    public static function default(array $disabled = [], bool $strict = true): self
    {
        return new self([
            new ActionGotoTargetResolvesRule(),
            new ActionRetryLimitsRule(),
            new ActionReusableRefResolvesRule(),
            new ActionTypeValidRule(),
            new AsyncApiFieldsRequire11Rule(),
            new ComponentsUniqueNamesRule(),
            new DocUnknownFieldRule(),
            new DocumentArazzoVersionRule(),
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
