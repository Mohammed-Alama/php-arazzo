<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;

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
        // @phpstan-ignore isset.property
        if (isset($doc->sourceDescriptions) && is_iterable($doc->sourceDescriptions)) {
            foreach ($doc->sourceDescriptions as $s) {
                // @phpstan-ignore isset.property
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

        // @phpstan-ignore isset.property, instanceof.alwaysTrue, booleanAnd.alwaysTrue
        if (isset($doc->components) && $doc->components instanceof Components) {
            $components = [
                // @phpstan-ignore isset.property
                'inputs' => self::keysOf(isset($doc->components->inputs) ? $doc->components->inputs : null),
                // @phpstan-ignore isset.property
                'parameters' => self::keysOf(isset($doc->components->parameters) ? $doc->components->parameters : null),
                // @phpstan-ignore isset.property
                'successActions' => self::keysOf(isset($doc->components->successActions) ? $doc->components->successActions : null),
                // @phpstan-ignore isset.property
                'failureActions' => self::keysOf(isset($doc->components->failureActions) ? $doc->components->failureActions : null),
            ];
        }

        $workflows = [];
        // @phpstan-ignore isset.property
        if (isset($doc->workflows) && is_iterable($doc->workflows)) {
            foreach ($doc->workflows as $wf) {
                // @phpstan-ignore isset.property
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

        // @phpstan-ignore isset.property
        if (isset($wf->parameters) && is_iterable($wf->parameters)) {
            foreach ($wf->parameters as $p) {
                // @phpstan-ignore isset.property
                if ($p instanceof Parameter && isset($p->name)) {
                    $params[$p->name] = true;
                }
            }
        }

        // @phpstan-ignore isset.property
        if (isset($wf->steps) && is_iterable($wf->steps)) {
            foreach ($wf->steps as $i => $s) {
                // @phpstan-ignore isset.property
                if ($s instanceof Step && isset($s->stepId)) {
                    $outs = [];
                    // @phpstan-ignore isset.property
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

        // @phpstan-ignore isset.property
        if (isset($wf->outputs) && is_iterable($wf->outputs)) {
            foreach ($wf->outputs as $k => $_) {
                $outputs[(string) $k] = true;
            }
        }

        // @phpstan-ignore isset.property
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
