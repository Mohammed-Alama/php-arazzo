<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Contracts;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Runner\WorkflowContext;
use Psr\Http\Message\RequestInterface;

interface RequestCompilerInterface
{
    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface;
}
