<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation;

use Flow\JSONPath\JSONPath;

class JsonPathEvaluator
{
    /**
     * @param array<array-key, mixed>|object $data
     */
    public static function evaluate(string $expression, array|object $data): mixed
    {
        $jsonPath = new JSONPath($data);
        $result = $jsonPath->find($expression);

        $arrayResult = $result->getData();

        // If single match and it was a specific pluck, sometimes it returns array of 1.
        // For simplicity in workflows, if we get 1 item back from a direct property accessor we might want to unwrap.
        // But standard JSONPath returns collections. We'll return the raw array data.
        return count($arrayResult) === 1 ? $arrayResult[0] : $arrayResult;
    }
}
