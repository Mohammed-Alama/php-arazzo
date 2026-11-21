<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ComponentsUniqueNamesRule implements Rule
{
    /** The Arazzo spec map key pattern: ^[a-zA-Z0-9.\-_]+$ */
    private const PATTERN = '/^[a-zA-Z0-9.\-_]+$/';

    /** @var list<string> */
    private const MAPS = ['inputs', 'parameters', 'successActions', 'failureActions'];

    public function code(): string
    {
        return 'components.unique_names';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $components = $doc->components;

        foreach (self::MAPS as $map) {
            /** @var array<string,mixed> $items */
            $items = $components->{$map} ?? [];

            foreach ($items as $key => $_) {
                if (preg_match(self::PATTERN, $key) !== 1) {
                    $errors->error(
                        'components.key_pattern',
                        "components.{$map} key '{$key}' must match [a-zA-Z0-9._-]+.",
                        "/components/{$map}/{$key}",
                    );
                }
            }
        }
    }
}
