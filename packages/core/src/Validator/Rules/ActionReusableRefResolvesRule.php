<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Reusable;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;

final class ActionReusableRefResolvesRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $w) {
            foreach ($w->steps as $si => $s) {
                $this->checkList($s->onSuccess, 'successActions', $symbols, $errors, "/workflows/{$wi}/steps/{$si}/onSuccess");
                $this->checkList($s->onFailure, 'failureActions', $symbols, $errors, "/workflows/{$wi}/steps/{$si}/onFailure");
            }
        }
    }

    /** @param list<mixed> $items */
    private function checkList(array $items, string $componentType, SymbolTable $symbols, ErrorCollector $errors, string $base): void
    {
        foreach ($items as $i => $item) {
            if (!$item instanceof Reusable) {
                continue;
            }
            $prefix = "\$components.{$componentType}.";
            if (!str_starts_with($item->reference, $prefix)) {
                $errors->error($this->code(), "Reusable reference '{$item->reference}' does not target components.{$componentType}.", "{$base}/{$i}/reference");

                continue;
            }
            $name = substr($item->reference, strlen($prefix));
            if (!isset($symbols->components[$componentType][$name])) {
                $errors->error($this->code(), "Reusable reference '{$item->reference}' does not resolve.", "{$base}/{$i}/reference");
            }
        }
    }

    public function code(): string
    {
        return 'action.reusable_ref_resolves';
    }
}
