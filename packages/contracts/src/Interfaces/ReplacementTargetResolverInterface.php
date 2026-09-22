<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

interface ReplacementTargetResolverInterface extends PluginInterface
{
    /**
     * @param  string  $targetType  json-pointer | xpath | proto-field
     */
    public function supports(string $targetType): bool;

    public function resolve(mixed $container, string $target, mixed $value): mixed;
}
