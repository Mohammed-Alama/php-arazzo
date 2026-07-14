<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Support;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\RequestBody;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\SuccessCriterion;
use Alama\LaravelArazzo\Dto\Workflow;

final class Fx
{
    /**
     * @param list<Workflow> $workflows
     * @param list<SourceDescription> $sources
     * @param array<string,mixed> $extensions
     * @param array<string,mixed>|null $rawRoot
     */
    public static function doc(
        array $workflows = [],
        array $sources = [],
        array $extensions = [],
        ?array $rawRoot = null,
        ?Components $components = null,
    ): ArazzoDocument {
        return new ArazzoDocument(
            '1.0.0',
            new Info('T', null, null, '1'),
            $sources,
            $workflows,
            $components ?? new Components([], [], [], []),
            $extensions,
            $rawRoot,
        );
    }

    /**
     * @param list<Parameter> $params
     * @param list<SuccessCriterion> $crit
     * @param list<mixed> $onSuccess
     * @param list<mixed> $onFailure
     * @param array<string,Expression> $outputs
     */
    public static function step(
        string $id = 's',
        ?string $opId = 'op',
        ?string $opPath = null,
        ?string $wfId = null,
        array $params = [],
        ?RequestBody $body = null,
        array $crit = [],
        array $onSuccess = [],
        array $onFailure = [],
        array $outputs = [],
    ): Step {
        return new Step($id, null, $opId, $opPath, $wfId, $params, $body, $crit, $onSuccess, $onFailure, $outputs);
    }

    /**
     * @param list<Step> $steps
     * @param list<string> $dep
     * @param array<string,mixed>|null $inputs
     * @param array<string,Expression> $outputs
     * @param list<Parameter> $parameters
     */
    public static function wf(
        string $id,
        array $steps,
        array $dep = [],
        ?array $inputs = null,
        array $outputs = [],
        array $parameters = [],
    ): Workflow {
        return new Workflow($id, null, null, $inputs, $dep, $steps, [], [], $outputs, $parameters);
    }
}
