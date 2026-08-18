<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution\Contracts;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Execution\WorkflowContext;
use Psr\Http\Message\RequestInterface;

interface RequestCompilerInterface
{
    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface;
}
