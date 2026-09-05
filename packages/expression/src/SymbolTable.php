<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Expression\Data\StepSymbols;
use Alama\Arazzo\Expression\Data\WorkflowSymbols;

final readonly class SymbolTable
{
    /**
     * @param  array<string,WorkflowSymbols>  $workflows
     * @param  array<string,true>  $sourceDescriptions
     * @param  array<string,array<string,true>>  $components
     */
    public function __construct(
        public array $workflows,
        public array $sourceDescriptions,
        public array $components,
    ) {}

    public static function build(ArazzoDocument $doc): self
    {
        $sources = [];
        if (isset($doc->sourceDescriptions) && is_iterable($doc->sourceDescriptions)) {
            foreach ($doc->sourceDescriptions as $s) {
                if ($s instanceof SourceDescription && isset($s->name)) {
                    $sources[$s->name] = true;
                }
            }
        }

        $components = [
            'inputs' => [],
            'parameters' => [],
            'successActions' => [],
            'failureActions' => [],
        ];

        if (isset($doc->components) && $doc->components instanceof Components) {
            $components = [
                'inputs' => self::keysOf(isset($doc->components->inputs) ? $doc->components->inputs : null),
                'parameters' => self::keysOf(isset($doc->components->parameters) ? $doc->components->parameters : null),
                'successActions' => self::keysOf(isset($doc->components->successActions) ? $doc->components->successActions : null),
                'failureActions' => self::keysOf(isset($doc->components->failureActions) ? $doc->components->failureActions : null),
            ];
        }

        $workflows = [];
        if (isset($doc->workflows) && is_iterable($doc->workflows)) {
            foreach ($doc->workflows as $wf) {
                if ($wf instanceof Workflow && isset($wf->workflowId)) {
                    $workflows[$wf->workflowId] = self::buildWorkflow($wf);
                }
            }
        }

        return new self($workflows, $sources, $components);
    }

    /**
     * @return array<string,true>
     */
    private static function keysOf(mixed $arr): array
    {
        $out = [];
        if (is_iterable($arr)) {
            foreach ($arr as $k => $_) {
                $out[(string) $k] = true;
            }
        }

        return $out;
    }

    private static function buildWorkflow(Workflow $wf): WorkflowSymbols
    {
        $inputs = [];
        $params = [];
        $steps = [];
        $outputs = [];
        $dependsOn = [];

        if (isset($wf->inputs['properties']) && is_iterable($wf->inputs['properties'])) {
            foreach ($wf->inputs['properties'] as $k => $_) {
                $inputs[(string) $k] = true;
            }
        }

        if (isset($wf->parameters) && is_iterable($wf->parameters)) {
            foreach ($wf->parameters as $p) {
                if ($p instanceof Parameter && isset($p->name)) {
                    $params[$p->name] = true;
                }
            }
        }

        if (isset($wf->steps) && is_iterable($wf->steps)) {
            foreach ($wf->steps as $i => $s) {
                if ($s instanceof Step && isset($s->stepId)) {
                    $outs = [];
                    if (isset($s->outputs) && is_iterable($s->outputs)) {
                        foreach ($s->outputs as $k => $_) {
                            $outs[(string) $k] = true;
                        }
                    }
                    $stepDependsOn = [];
                    foreach ($s->dependsOn as $d) {
                        $stepDependsOn[$d] = true;
                    }
                    $steps[$s->stepId] = new StepSymbols($outs, (int) $i, $stepDependsOn);
                }
            }
        }

        if (isset($wf->outputs) && is_iterable($wf->outputs)) {
            foreach ($wf->outputs as $k => $_) {
                $outputs[(string) $k] = true;
            }
        }

        if (isset($wf->dependsOn) && is_iterable($wf->dependsOn)) {
            foreach ($wf->dependsOn as $d) {
                if (is_string($d) || is_int($d)) {
                    $dependsOn[(string) $d] = true;
                }
            }
        }

        return new WorkflowSymbols($inputs, $params, $steps, $outputs, $dependsOn);
    }
}
