<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator;

use Alama\Arazzo\Expression\Xpath\XpathEvaluator;
use Alama\Arazzo\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Resolver\SourceRegistry;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Reusable;
use Alama\Arazzo\Spec\Selector;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use JsonSchema\Constraints\Constraint;
use JsonSchema\SchemaStorage;
use JsonSchema\Validator;

/**
 * Execution preflight: resolves every capability a workflow run will need
 * BEFORE any side effect (no HTTP, no queue, no ledger writes).
 *
 * Diagnostics are stable-coded (`preflight.*`), carry severity and a
 * pointer that embeds the workflow/step location.
 */
final class PreflightValidator
{
    private OpenApiVersionDetector $versionDetector;

    public function __construct(
        private readonly SourceRegistry $sources,
        private readonly OpenApiOperationResolver $operations,
        private readonly XpathEvaluator $xpath,
    ) {
        $this->versionDetector = new OpenApiVersionDetector();
    }

    public function validate(ArazzoDocument $document): ValidationResult
    {
        $errors = new ErrorCollector();

        foreach ($document->workflows as $workflow) {
            foreach ($workflow->steps as $step) {
                $this->checkStep($document, $workflow, $step, $errors);
            }
        }

        return new ValidationResult($document, $errors->errors(), $errors->warnings());
    }

    /**
     * Validates supplied runtime inputs against the workflow's declared
     * `inputs` JSON Schema (2020-12) BEFORE any side effect. First-mover
     * capability: no surveyed Arazzo tool does this.
     *
     * @param  array<string, mixed>  $inputs
     */
    public function validateInputs(ArazzoDocument $document, string $workflowId, array $inputs): ValidationResult
    {
        $errors = new ErrorCollector();

        $workflow = null;

        foreach ($document->workflows as $candidate) {
            if ($candidate->workflowId === $workflowId) {
                $workflow = $candidate;
                break;
            }
        }

        if ($workflow === null) {
            return new ValidationResult($document, [
                new Error('preflight.unknown_workflow', "Workflow '{$workflowId}' does not exist.", '/workflows/'.$workflowId),
            ], []);
        }

        $schema = is_array($workflow->inputs ?? null) ? $workflow->inputs : null;

        // No declared schema = anything goes.
        if ($schema === null || $schema === []) {
            return new ValidationResult($document, [], []);
        }

        $violations = $this->validateAgainstSchema($inputs, $schema);

        foreach ($violations as $violation) {
            $pointer = '/workflows/'.$workflowId.'/inputs'
                .($violation['property'] !== ''
                    ? (string) preg_replace('/^\[([^\]]+)\]/', '/$1', $violation['property'])
                    : '');

            $errors->add(new Error(
                'preflight.inputs_schema',
                $violation['message'],
                $pointer,
                severity: Severity::Error,
            ));
        }

        return new ValidationResult($document, $errors->errors(), $errors->warnings());
    }

    /**
     * Runs the justinrainbow validator against a raw 2020-12 schema array.
     *
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $schema
     * @return list<array{message: string, property: string}>
     */
    private function validateAgainstSchema(array $inputs, array $schema): array
    {
        $storage = new SchemaStorage();
        $schemaObject = \json_decode((string) \json_encode($schema), false);

        if (!$schemaObject instanceof \stdClass) {
            return [];
        }

        $schemaId = 'memory://inputs-schema';
        $storage->addSchema($schemaId, $schemaObject);

        $validator = new Validator();
        $dataObject = \json_decode((string) \json_encode($inputs ?: new \stdClass()), false);

        $validator->validate(
            $dataObject,
            $storage->getSchema($schemaId),
            Constraint::CHECK_MODE_VALIDATE_SCHEMA | Constraint::CHECK_MODE_APPLY_DEFAULTS,
        );

        if ($validator->isValid()) {
            return [];
        }

        $out = [];

        /** @var array{message: string, property?: string} $error */
        foreach ($validator->getErrors() as $error) {
            $out[] = [
                'message' => (string) $error['message'],
                'property' => is_string($error['property'] ?? null) ? $error['property'] : '',
            ];
        }

        return $out;
    }

    private function checkStep(ArazzoDocument $document, Workflow $workflow, Step $step, ErrorCollector $errors): void
    {
        $base = '/workflows/'.$workflow->workflowId.'/steps/'.$step->stepId;

        // 1. The referenced source must exist and be locally available so
        //    resolution never triggers a network fetch during preflight.
        $sourceName = $this->sourceNameOf($step);

        if ($sourceName !== null) {
            $description = null;
            foreach ($document->sourceDescriptions as $candidate) {
                if ($candidate->name === $sourceName) {
                    $description = $candidate;
                    break;
                }
            }

            if ($description === null) {
                $errors->add(new Error(
                    'preflight.source_unresolved',
                    "Step '{$step->stepId}' references unknown source description '{$sourceName}'.",
                    $base.'/operationPath',
                    severity: Severity::Error,
                ));

                return;
            }

            if ($this->sources->get($sourceName) === null) {
                // Non-blocking: remote sources are legitimate at runtime;
                // preflight simply cannot verify them without a fetch.
                $errors->add(new Warning(
                    'preflight.source_not_local',
                    "Source '{$sourceName}' is not pre-registered; preflight cannot resolve it without a fetch.",
                    $base.'/operationPath',
                ));

                return;
            }

            // 2. The OpenAPI document version must be supported.
            $openapi = $this->sources->get($sourceName)->content;
            if ($openapi !== []) {
                try {
                    $version = $this->versionDetector->detect($openapi);
                } catch (\InvalidArgumentException) {
                    $version = 'unknown';
                }

                if (!in_array($version, ['2.0', '3.0', '3.1'], true)) {
                    $errors->add(new Error(
                        'preflight.unsupported_openapi_version',
                        "Source '{$sourceName}' declares unsupported OpenAPI version '{$version}'.",
                        '/sourceDescriptions/'.$sourceName,
                        severity: Severity::Error,
                    ));
                }
            }

            // 2. Operation reference resolves against the local document.
            try {
                $this->operations->resolve($step, $document);
            } catch (\Throwable $e) {
                $errors->add(new Error(
                    'preflight.operation_unresolvable',
                    "Step '{$step->stepId}' operation reference failed: {$e->getMessage()}",
                    $base.'/operationPath',
                    severity: Severity::Error,
                ));

                return;
            }
        }

        // 4. Reusable action references must point at existing components.
        foreach ([$step->onSuccess, $step->onFailure] as $actions) {
            foreach ($actions as $action) {
                if (!$action instanceof Reusable) {
                    continue;
                }

                $component = str_starts_with($action->reference, 'failureActions.')
                    ? ($document->components->failureActions[substr($action->reference, strlen('failureActions.'))] ?? null)
                    : ($document->components->successActions[substr($action->reference, strlen('successActions.'))] ?? null);

                if ($component === null) {
                    $errors->add(new Error(
                        'preflight.reusable_action_missing',
                        "Reusable action reference '{$action->reference}' does not resolve to a component.",
                        $base.'/onSuccess',
                        severity: Severity::Error,
                    ));
                }
            }
        }

        // 5. Selector XPath versions must be supported by the bound evaluator.
        foreach ($this->selectorsOf($step) as $selector) {
            $version = $selector->version ?? null;
            if ($version !== null && $selector->type->value === 'xpath') {
                $supported = $this->xpath->supportedVersions();

                if (!in_array($version, $supported, true)) {
                    $errors->add(new Error(
                        'preflight.unsupported_selector_version',
                        "Selector requests XPath version '{$version}'; evaluator supports: ".implode(', ', $supported).'.',
                        $base.'/parameters',
                        severity: Severity::Error,
                    ));
                }
            }
        }
    }

    private function sourceNameOf(Step $step): ?string
    {
        foreach ([$step->operationPath, $step->operationId] as $reference) {
            if (is_string($reference) && preg_match('/^\{\$sourceDescriptions\.([^}]+)\.url\}/', $reference, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }

    /** @return list<Selector> */
    private function selectorsOf(Step $step): array
    {
        $selectors = [];

        foreach ($step->parameters as $parameter) {
            if ($parameter->value instanceof Selector) {
                $selectors[] = $parameter->value;
            }
        }

        foreach ($step->outputs as $value) {
            if ($value instanceof Selector) {
                $selectors[] = $value;
            }
        }

        return $selectors;
    }
}
