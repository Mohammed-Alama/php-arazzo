<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * graphql-step rules (PR #567):
 *  - schema is a whole-source reference ($sourceDescriptions.<name>)
 *  - operation is required
 *  - extensions and extensionsSelector are mutually exclusive
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class GraphQlStepRule implements Rule
{
    public function code(): string
    {
        return 'step.graphql_step';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                $operation = $s->target->graphqlOperation;
                if ($operation === null) {
                    continue;
                }

                $path = "/workflows/{$i}/steps/{$j}";

                if ($operation->schema === '' || preg_match('/^\$sourceDescriptions\.[A-Za-z0-9_-]+$/', $operation->schema) !== 1) {
                    $errors->error(
                        'step.graphql_step',
                        "Step '{$s->stepId}' GraphQL schema must be a whole-source reference like \$sourceDescriptions.<name>.",
                        $path,
                    );
                }

                if ($operation->operation === '' || trim($operation->operation) === '') {
                    $errors->error('step.graphql_step', "Step '{$s->stepId}' GraphQL operation is required.", $path);
                }

                if ($operation->extensions !== null && $operation->extensionsSelector !== null) {
                    $errors->error(
                        'step.graphql_step',
                        "Step '{$s->stepId}' extensions and extensionsSelector are mutually exclusive.",
                        $path,
                    );
                }
            }
        }
    }
}
