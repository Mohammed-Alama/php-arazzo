<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;

final class WorkflowInputsValidSchemaRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            if (!isset($w->inputs)) {
                continue;
            }
            $path = "/workflows/{$i}/inputs";
            if (is_array($w->inputs) && array_is_list($w->inputs) && $w->inputs !== []) {
                $errors->error($this->code(), 'workflow inputs must be an object.', $path);

                continue;
            }
            if (isset($w->inputs['type']) && $w->inputs['type'] !== 'object') {
                $errors->error($this->code(), "workflow inputs schema must be of type 'object'.", $path . '/type');

                continue;
            }
            if (isset($w->inputs['properties']) && !is_array($w->inputs['properties'])) {
                $errors->error($this->code(), 'workflow inputs.properties must be an object.', $path . '/properties');
            }
        }
    }

    public function code(): string
    {
        return 'workflow.inputs_valid_schema';
    }
}
