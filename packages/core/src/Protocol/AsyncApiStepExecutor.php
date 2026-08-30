<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol;

use Alama\Arazzo\Evaluation\PayloadReplacer;
use Alama\Arazzo\Execution\Exceptions\ExecutionException;
use Alama\Arazzo\Execution\ReusableParameterResolver;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Infrastructure\Interfaces\HttpClientInterface;
use Alama\Arazzo\Protocol\Interfaces\StepProtocolExecutorInterface;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\SpecVersion;
use Alama\Arazzo\Spec\EvaluationContext;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\PayloadReplacement;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\StepExecutionOutcome;
use Alama\Arazzo\Spec\WorkflowContext;
use Alama\Arazzo\State\Interfaces\PendingCorrelationRegistryInterface;
use JsonException;
use LogicException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

final class AsyncApiStepExecutor implements StepProtocolExecutorInterface
{
    public function __construct(
        private PendingCorrelationRegistryInterface $pendingCorrelations,
        private ExpressionEvaluator $evaluator,
        private HttpClientInterface $httpClient,
        private ?RequestFactoryInterface $requestFactory = null,
        private ?StreamFactoryInterface $streamFactory = null,
        private ?UriFactoryInterface $uriFactory = null,
    ) {}

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return in_array($step->action, ['send', 'receive'], true);
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        if ($document->specVersion === SpecVersion::V1_0) {
            throw new LogicException(
                "AsyncAPI step '{$step->stepId}' encountered under a 1.0.0 document; upgrade to arazzo: 1.1.0.",
            );
        }

        if ($step->action === 'send') {
            $message = $this->compileMessage($step, $context, $document);
            $response = $this->httpClient->sendRequest($message, $step->timeout !== null ? $step->timeout / 1000 : null);

            return StepExecutionOutcome::resolved($response->getStatusCode(), [], []);
        }

        if ($step->action !== 'receive') {
            throw new LogicException("Unsupported action '{$step->action}' for step '{$step->stepId}'.");
        }

        if ($step->correlationId === null) {
            throw new LogicException("Step '{$step->stepId}' has action 'receive' but no correlationId expression.");
        }
        if ($step->channelPath === null) {
            throw new LogicException("Step '{$step->stepId}' has action 'receive' but no channelPath.");
        }

        $correlationId = (string) $this->evaluator->evaluate($step->correlationId, new EvaluationContext($context, $step->stepId, $document));

        $this->pendingCorrelations->create($correlationId, $executionId, $step->stepId, $step->channelPath, $step->timeout !== null ? $step->timeout : null);

        return StepExecutionOutcome::suspended();
    }

    /**
     * Compiles an outgoing message into a POST request targeting the step's
     * channelPath. Parameters become query arguments (or headers when declared
     * `in: header`); the requestBody payload, with its replacements applied,
     * becomes the JSON body.
     */
    private function compileMessage(Step $step, WorkflowContext $context, ArazzoDocument $document): RequestInterface
    {
        $factory = $this->requestFactory
            ?? throw ExecutionException::messageFactoryMissing($step->stepId);
        $streams = $this->streamFactory
            ?? throw ExecutionException::messageFactoryMissing($step->stepId);
        $uris = $this->uriFactory
            ?? throw ExecutionException::messageFactoryMissing($step->stepId);

        $uri = $this->resolveChannelUri($step);

        $evaluationContext = new EvaluationContext($context, $step->stepId, $document);

        $query = [];
        $headers = [];
        $parameters = new ReusableParameterResolver()->resolve($step->parameters, $document);
        foreach ($parameters as $parameter) {
            $value = $parameter->value instanceof Expression
                ? $this->evaluator->evaluate($parameter->value, $evaluationContext)
                : $parameter->value;

            if ($parameter->in?->value === 'header') {
                $headers[$parameter->name] = self::stringify($value);

                continue;
            }

            $query[$parameter->name] = $value;
        }

        $payload = $this->buildPayload($step, $evaluationContext);

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new LogicException("Step '{$step->stepId}' produced an unencodable message payload: {$e->getMessage()}");
        }

        // Merge step parameters over any query arguments embedded in the channel URI.
        $existing = [];
        parse_str($uri->getQuery(), $existing);

        $target = $uri->withQuery(http_build_query(array_merge($existing, $query)));

        $request = $factory
            ->createRequest('POST', $target)
            ->withHeader('Content-Type', 'application/json');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request->withBody($streams->createStream($encoded));
    }

    private function resolveChannelUri(Step $step): UriInterface
    {
        $channelPath = $step->channelPath
            ?? throw new LogicException("Send step '{$step->stepId}' has no channelPath.");

        if (preg_match('#^https?://#i', $channelPath) !== 1) {
            throw ExecutionException::unresolvableChannelTarget($step->stepId, $channelPath);
        }

        return ($this->uriFactory ?? throw ExecutionException::messageFactoryMissing($step->stepId))
            ->createUri($channelPath);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function buildPayload(Step $step, EvaluationContext $context): array
    {
        $requestBody = $step->requestBody;

        return $requestBody !== null && is_array($requestBody->payload)
            ? PayloadReplacer::apply(
                $step,
                $requestBody->payload,
                fn (PayloadReplacement $replacement) => $replacement->value instanceof Expression
                    ? $this->evaluator->evaluate($replacement->value, $context)
                    : $replacement->value,
            )
            : [];
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
