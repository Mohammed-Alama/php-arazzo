<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Support\InputSchemaResolver;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Interfaces\Rule;

final class WorkflowInputsValidSchemaRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            if (!isset($w->inputs)) {
                continue;
            }
            $path = "/workflows/{$i}/inputs";

            // Local references into components.inputs resolve before shape checks.
            $inputs = InputSchemaResolver::resolve($w->inputs, $doc->components->inputs) ?? [];

            if (array_is_list($inputs) && $inputs !== []) {
                $errors->error($this->code(), 'workflow inputs must be an object.', $path);

                continue;
            }
            if (isset($inputs['type']) && $inputs['type'] !== 'object') {
                $errors->error($this->code(), "workflow inputs schema must be of type 'object'.", $path.'/type');

                continue;
            }
            if (isset($inputs['properties']) && !is_array($inputs['properties'])) {
                $errors->error($this->code(), 'workflow inputs.properties must be an object.', $path.'/properties');
            }
        }
    }

    public function code(): string
    {
        return 'workflow.inputs_valid_schema';
    }
}
