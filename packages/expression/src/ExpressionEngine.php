<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\PayloadReplacement;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Expression\Ast\ComponentRef;
use Alama\Arazzo\Expression\Ast\ExpressionAst;
use Alama\Arazzo\Expression\Ast\HttpMetaRef;
use Alama\Arazzo\Expression\Ast\InputPart;
use Alama\Arazzo\Expression\Ast\InputRef;
use Alama\Arazzo\Expression\Ast\MessageRef;
use Alama\Arazzo\Expression\Ast\OutputPart;
use Alama\Arazzo\Expression\Ast\OutputRef;
use Alama\Arazzo\Expression\Ast\RequestPart;
use Alama\Arazzo\Expression\Ast\ResponsePart;
use Alama\Arazzo\Expression\Ast\SourceRef;
use Alama\Arazzo\Expression\Ast\StepRef;
use Alama\Arazzo\Expression\Ast\WorkflowRef;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Enum\ReferenceKind;
use Alama\Arazzo\Expression\Evaluation\CriteriaEvaluator;
use Alama\Arazzo\Expression\Evaluation\InterpolationResolver;
use Alama\Arazzo\Expression\Evaluation\PayloadReplacer;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Expression\Parser as ExpressionParser;
use Alama\Arazzo\Expression\Xpath\DomXpathEvaluator;

/**
 * Concrete expression facade.
 *
 * A thin, self-contained object that hides the expression engine internals
 * (lexer/AST, evaluator, jsonpath/xpath) behind a single entry point.
 *
 * Delegates every capability to the existing internal services.
 */
final class ExpressionEngine implements ExpressionEngineInterface
{
    public function __construct(
        private readonly ExpressionEvaluator $evaluator = new ExpressionEvaluator(),
        private readonly ExpressionParser $parser = new ExpressionParser(),
        private readonly DomXpathEvaluator $xpath = new DomXpathEvaluator(),
    ) {}

    private ?CriteriaEvaluator $criteriaEvaluator = null;

    private ?SelectorEvaluator $selectorEvaluator = null;

    private ?StringInterpolator $interpolator = null;

    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
    {
        return $this->evaluator->evaluate($expression, $context);
    }

    public function buildSymbolTable(ArazzoDocument $document): SymbolTable
    {
        return SymbolTable::build($document);
    }

    public function parseExpression(string $raw): ?ExpressionSyntaxException
    {
        $result = $this->parser->parseOrError($raw);

        return $result instanceof ExpressionSyntaxException ? $result : null;
    }

    public function expressionReferences(string $raw): ?ExpressionReference
    {
        $result = $this->parser->parseOrError($raw);
        if ($result instanceof ExpressionSyntaxException) {
            return null;
        }

        return $this->referenceFor($result);
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return $this->criteria()->evaluateCriteria($criteria, $step, $context, $document);
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return $this->criteria()->evaluateSuccessCriteria($step, $context, $document);
    }

    public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed
    {
        return $this->selectors()->evaluate($selector, $context, $stepId);
    }

    public function queryXPath(mixed $rootValue, string $selector, string $version): mixed
    {
        return $this->xpath->query($rootValue, $selector, $version);
    }

    public function supportedXPathVersions(): array
    {
        return $this->xpath->supportedVersions();
    }

    public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string
    {
        return $this->interpolator()->interpolate($value, $context, $stepId);
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @param  callable(PayloadReplacement): mixed|null  $resolveValue
     * @return array<array-key, mixed>
     */
    public function replacePayload(Step $step, array $body, ?callable $resolveValue = null, ?WorkflowContext $context = null): array
    {
        return PayloadReplacer::apply($step, $body, $resolveValue, $context);
    }

    public function jsonPath(string $expression, array|object $data): mixed
    {
        return JsonPathEvaluator::evaluate($expression, $data);
    }

    public function jsonPointer(array $data, ?string $pointer): mixed
    {
        return JsonPointer::resolve($data, $pointer);
    }

    private function criteria(): CriteriaEvaluator
    {
        return $this->criteriaEvaluator ??= new CriteriaEvaluator($this->evaluator);
    }

    private function selectors(): SelectorEvaluator
    {
        return $this->selectorEvaluator ??= new SelectorEvaluator($this->xpath, $this->evaluator);
    }

    private function interpolator(): StringInterpolator
    {
        return $this->interpolator ??= new StringInterpolator(new InterpolationResolver($this->evaluator));
    }

    private function referenceFor(ExpressionAst $ast): ExpressionReference
    {
        if ($ast instanceof InputRef) {
            return new ExpressionReference(ReferenceKind::Input, $ast->name, jsonPointer: $ast->jsonPointer);
        }

        if ($ast instanceof OutputRef) {
            return new ExpressionReference(ReferenceKind::Output, $ast->name, jsonPointer: $ast->jsonPointer);
        }

        if ($ast instanceof StepRef) {
            return $this->stepReference($ast);
        }

        if ($ast instanceof WorkflowRef) {
            return new ExpressionReference(ReferenceKind::Workflow, $ast->workflowId, $ast->partKind, $ast->name);
        }

        if ($ast instanceof SourceRef) {
            return new ExpressionReference(ReferenceKind::Source, $ast->name, name: $ast->subPath);
        }

        if ($ast instanceof ComponentRef) {
            return new ExpressionReference(ReferenceKind::Component, $ast->type, name: $ast->name);
        }

        if ($ast instanceof MessageRef) {
            return new ExpressionReference(ReferenceKind::Message, part: $ast->part, name: $ast->name, jsonPointer: $ast->jsonPointer);
        }

        if ($ast instanceof HttpMetaRef) {
            return new ExpressionReference(ReferenceKind::HttpMeta, httpPart: $ast->field);
        }

        return new ExpressionReference(ReferenceKind::Self);
    }

    private function stepReference(StepRef $ast): ExpressionReference
    {
        $part = $ast->part;

        if ($part instanceof OutputPart) {
            return new ExpressionReference(ReferenceKind::Step, $ast->stepId, 'outputs', $part->name, jsonPointer: $part->jsonPointer);
        }

        if ($part instanceof InputPart) {
            return new ExpressionReference(ReferenceKind::Step, $ast->stepId, 'inputs', $part->name);
        }

        if ($part instanceof RequestPart) {
            return new ExpressionReference(ReferenceKind::Step, $ast->stepId, 'request', $part->headerName, $part->httpPart, jsonPointer: $part->jsonPointer);
        }

        if ($part instanceof ResponsePart) {
            return new ExpressionReference(ReferenceKind::Step, $ast->stepId, 'response', $part->headerName, $part->httpPart, jsonPointer: $part->jsonPointer);
        }

        return new ExpressionReference(ReferenceKind::Step, $ast->stepId);
    }
}
