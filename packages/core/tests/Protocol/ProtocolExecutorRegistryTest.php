<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\StepExecutionOutcome;
use Alama\Arazzo\Contracts\StepProtocolExecutorInterface;
use Alama\Arazzo\Contracts\WorkflowContext;
use Alama\Arazzo\Protocol\ProtocolExecutorRegistry;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Step;

class RegistryFakeExecutor implements StepProtocolExecutorInterface
{
    public function __construct(private readonly bool $supports = true) {}

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return $this->supports;
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        return StepExecutionOutcome::resolved(200, [], []);
    }
}

function registryDocument(): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1.0.0'), [], [], new Components([], [], [], []), []);
}

it('resolves the first registered executor whose supports() matches', function (): void {
    $specific = new RegistryFakeExecutor(supports: true);
    $generic = new RegistryFakeExecutor(supports: true);

    $registry = new ProtocolExecutorRegistry();
    $registry->register('sub-workflow', $specific);
    $registry->register('http', $generic);

    $resolved = $registry->resolve(new Step('s', null, null, null, null, [], null, [], [], [], []), registryDocument());

    expect($resolved)->toBe($specific)
        ->and($registry->getSupportedProtocols())->toBe(['sub-workflow', 'http']);
});

it('skips non-matching executors and returns null when none support the step', function (): void {
    $registry = new ProtocolExecutorRegistry();
    $registry->register('a', new RegistryFakeExecutor(supports: false));

    expect($registry->resolve(new Step('s', null, null, null, null, [], null, [], [], [], []), registryDocument()))->toBeNull();
});

it('returns null for an empty registry', function (): void {
    expect((new ProtocolExecutorRegistry())->resolve(new Step('s', null, null, null, null, [], null, [], [], [], []), registryDocument()))->toBeNull()
        ->and((new ProtocolExecutorRegistry())->getSupportedProtocols())->toBe([]);
});
