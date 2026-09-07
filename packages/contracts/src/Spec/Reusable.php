<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

use RuntimeException;

final readonly class Reusable
{
    /**
     * @param  Expression|Selector|scalar|array<mixed>|null  $value
     */
    public function __construct(
        public string $reference,
        public mixed $value = null,
    ) {}

    public function getParamterComponent(?ArazzoDocument $document): ?Parameter
    {
        if (!preg_match('/^\$components\.parameters\.(.+)$/', $this->reference, $m) || $document === null) {
            return null;
        }

        $component = $document->components->parameters[$m[1]] ?? null;

        if ($component === null) {
            throw new RuntimeException(
                "Unresolvable reusable parameter reference '{$this->reference}'.",
            );
        }

        return $this->value !== null
            ? new Parameter(name: $component->name, in: $component->in, value: $this->value)
            : new Parameter(name: $component->name, in: $component->in, value: $component->value);
    }
}
