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
        $normalized = self::normalizeFilters($expression);

        // RFC 9535 allows filter selectors against OBJECT roots (testing the
        // object's own members); classic engines only filter lists, so wrap
        // the object as a single-element collection.
        $isAssocObject = is_array($data) && $data !== [] && !array_is_list($data);
        $wrapped = $isAssocObject && preg_match('/^\$?\[\?/', $normalized) === 1;

        if ($wrapped) {
            $data = [$data];
        }

        $jsonPath = new JSONPath($data);

        $result = $jsonPath->find($normalized);

        $arrayResult = $result->getData();

        // Single-match plucks unwrap to the value itself; standard JSONPath
        // collections stay collections. Wrapped object-filters keep their
        // collection shape so criteria truthiness sees "matched".
        if (!$wrapped && count($arrayResult) === 1) {
            return $arrayResult[0];
        }

        return $arrayResult;
    }

    /**
     * RFC 9535 spells filter selectors `[?@...]` / `[?count(...)]`; the
     * underlying engine uses the classic `[?(...)]` form. Translate the
     * modern spelling so official examples (oauth, BNPL) evaluate.
     */
    public static function normalizeFilters(string $expression): string
    {
        // `[?BODY]` -> `[?(BODY)]`
        return (string) preg_replace('/\[\?([^\]]*)\]/', '[?($1)]', $expression);
    }
}
