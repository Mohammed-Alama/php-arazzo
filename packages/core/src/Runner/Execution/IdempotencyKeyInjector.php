<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Spec\Step;
use Psr\Http\Message\RequestInterface;

final class IdempotencyKeyInjector
{
    private const MUTATING_METHODS = ['POST', 'PATCH', 'DELETE'];

    public function __construct(
        private bool $enabledDefault,
        private string $headerDefault,
    ) {
    }

    public function inject(RequestInterface $request, Step $step, WorkflowContext $context): InjectionResult
    {
        $enabled = $step->idempotencyKey ?? $this->enabledDefault;
        if (!$enabled) {
            return new InjectionResult($request);
        }

        if (!in_array(strtoupper($request->getMethod()), self::MUTATING_METHODS, true)) {
            return new InjectionResult($request);
        }

        $fingerprint = $this->requestFingerprint($request);

        $key = hash('sha256', implode('|', [
            (string) $context->getDefinitionId(),
            (string) $context->getWorkflowId(),
            $step->stepId,
            $fingerprint,
        ]));

        $header = $step->idempotencyHeader ?? $this->headerDefault;

        return new InjectionResult(
            request: $request->withHeader($header, $key),
            key: $key,
            header: $header,
        );
    }

    private function requestFingerprint(RequestInterface $request): string
    {
        $body = $request->getBody();
        $body->rewind();
        $raw = $body->getContents();
        $body->rewind();

        $canonical = $this->canonicalizeBody($raw);

        return hash('sha256', implode('|', [
            strtoupper($request->getMethod()),
            (string) $request->getUri(),
            $canonical,
        ]));
    }

    private function canonicalizeBody(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $raw;
        }

        $sorted = $this->recursivelySortAssociativeKeys($decoded);

        $reEncoded = json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $reEncoded === false ? $raw : $reEncoded;
    }

    /**
     * @param array<int|string,mixed> $value
     *
     * @return array<int|string,mixed>
     */
    private function recursivelySortAssociativeKeys(array $value): array
    {
        // Preserve positional semantics of list arrays; only sort key order on associative arrays.
        $isList = array_is_list($value);

        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->recursivelySortAssociativeKeys($v);
            }
        }

        if (!$isList) {
            ksort($value);
        }

        return $value;
    }
}
