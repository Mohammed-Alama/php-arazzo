<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Document\Validator\Support\WorkflowDependencyCycleWalker;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class WorkflowDependsOnNoCycleRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        (new WorkflowDependencyCycleWalker($symbols, $errors, $this->code()))->run($doc);
    }

    public function code(): string
    {
        return 'workflow.dependson_no_cycle';
    }
}
