<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Spec\Reusable;
use RuntimeException;

/**
 * Substitutes reusable parameter references ({reference: $components.parameters.x})
 * with the component's declared parameter at execution-prep time, so executors
 * only ever deal with concrete Parameter objects.
 */
final class ReusableParameterResolver
{
    /**
     * @param list<Parameter|Reusable> $parameters
     *
     * @return list<Parameter>
     */
    public function resolve(array $parameters, ?ArazzoDocument $document): array
    {
        $resolved = [];
        foreach ($parameters as $parameter) {
            if (!$parameter instanceof Reusable) {
                $resolved[] = $parameter;

                continue;
            }

            $component = $this->lookup($parameter->reference, $document);

            if ($component === null) {
                throw new RuntimeException(
                    "Unresolvable reusable parameter reference '{$parameter->reference}'.",
                );
            }

            // A value supplied alongside the reference overrides the component default.
            $resolved[] = $parameter->value !== null
                ? new Parameter(name: $component->name, in: $component->in, value: $parameter->value)
                : new Parameter(name: $component->name, in: $component->in, value: $component->value);
        }

        return $resolved;
    }

    private function lookup(string $reference, ?ArazzoDocument $document): ?Parameter
    {
        if (!preg_match('/^\$components\.parameters\.(.+)$/', $reference, $m) || $document === null) {
            return null;
        }

        return $document->components->parameters[$m[1]] ?? null;
    }
}
