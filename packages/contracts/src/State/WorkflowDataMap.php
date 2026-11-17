<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\State;

/**
 * Normalizes the persisted workflow-output map.
 *
 * Persisted payloads are untrusted arrays: only string-keyed entries with
 * string-keyed inputs/outputs survive hydration.
 *
 * @internal shared by the state DTO adapters; not part of the public API surface
 */
final class WorkflowDataMap
{
    /**
     * @return array<string, array{inputs: array<string, mixed>, outputs: array<string, mixed>}>
     */
    public static function from(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $workflows = [];
        foreach ($raw as $workflowId => $data) {
            if (!is_string($workflowId) || !is_array($data)) {
                continue;
            }

            $workflows[$workflowId] = self::merged($data, []);
        }

        return $workflows;
    }

    /**
     * @param  array<mixed, mixed>  $data
     * @param  array<mixed, mixed>  $existing
     * @return array{inputs: array<string, mixed>, outputs: array<string, mixed>}
     */
    public static function merged(array $data, array $existing): array
    {
        return [
            'inputs' => self::stringKeyed($data['inputs'] ?? $existing['inputs'] ?? []),
            'outputs' => self::stringKeyed($data['outputs'] ?? $existing['outputs'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function stringKeyed(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
