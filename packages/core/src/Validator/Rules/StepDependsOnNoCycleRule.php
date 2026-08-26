<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Dependency\DependencyGraph;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;

final class StepDependsOnNoCycleRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $workflowIndex => $workflow) {
            $graph = new DependencyGraph($workflow->steps);

            $indexOf = [];
            foreach ($workflow->steps as $i => $step) {
                $indexOf[$step->stepId] = $i;
            }

            $cycle = $graph->getCycle();
            if ($cycle !== null) {
                $path = implode(' -> ', $cycle);
                $stepId = $cycle[0];
                $stepIndex = $indexOf[$stepId] ?? 0;
                $errors->error(
                    'step.dependson_no_cycle',
                    "step.dependsOn cycle detected involving: {$path}.",
                    "/workflows/{$workflowIndex}/steps/{$stepIndex}/dependsOn",
                );
            }

            foreach ($graph->getUnresolvedReferences() as $stepId => $unresolved) {
                $stepIndex = $indexOf[$stepId] ?? 0;
                foreach ($unresolved as $ref) {
                    $errors->error(
                        'step.dependson_unresolved_reference',
                        "step.dependsOn reference '{$ref}' from step '{$stepId}' does not exist in workflow.",
                        "/workflows/{$workflowIndex}/steps/{$stepIndex}/dependsOn",
                    );
                }
            }
        }
    }

    public function code(): string
    {
        return 'step.dependson_validation';
    }
}
