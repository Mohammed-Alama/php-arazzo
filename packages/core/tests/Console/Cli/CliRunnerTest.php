<?php

declare(strict_types=1);

use Alama\Arazzo\Console\Cli\CliRunner;
use Alama\Arazzo\Execution\InMemoryDefinitionRegistry;
use Alama\Arazzo\Protocol\Interfaces\StepProtocolExecutorInterface;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\StepExecutionOutcome;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Spec\WorkflowContext;
use Alama\Arazzo\State\FileStateStore;
use Alama\Arazzo\Tests\Support\TestExpressionResolver;

class CliFakeExecutor implements StepProtocolExecutorInterface
{
    public function __construct(private readonly int $statusCode = 200) {}

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return true;
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        return StepExecutionOutcome::resolved($this->statusCode, ['from' => $step->stepId], [], inputs: []);
    }
}

function cliDocument(): ArazzoDocument
{
    $workflow = new Workflow('cli_wf', null, null, null, [], [
        new Step('one', null, null, null, null, [], null, [], [], [], []),
        new Step('two', null, null, null, null, [], null, [], [], [], [], ['one']),
    ], [], [], [], []);

    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('CLI', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [$workflow],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

/**
 * @return array{0: CliRunner, 1: InMemoryDefinitionRegistry}
 */
function cliRunner(FileStateStore $store): array
{
    $definitions = new InMemoryDefinitionRegistry();
    $definitions->register(cliDocument());

    $runner = new CliRunner(
        expressions: new TestExpressionResolver(),
        stateStore: $store,
        definitions: $definitions,
        protocolExecutors: [new CliFakeExecutor()],
    );

    return [$runner, $definitions];
}

function cliDefinitionIdOf(InMemoryDefinitionRegistry $definitions): string
{
    $ids = array_keys((new ReflectionClass($definitions))->getProperty('registry')->getValue($definitions));

    return $ids[0];
}

it('runs a linear workflow to completion in-process and persists final state to files', function (): void {
    $store = new FileStateStore(tempStateDir());
    [$runner] = cliRunner($store);

    $result = $runner->run(cliDocument(), 'cli_wf', ['k' => 'v'], 'cli_exec_1');

    expect($result->executionId)->toBe('cli_exec_1')
        ->and($result->status)->toBe('succeeded')
        ->and($result->succeeded())->toBeTrue()
        ->and($result->failed())->toBeFalse();

    // Final state is on disk, keyed by execution id.
    $persisted = $store->load('cli_exec_1');
    expect($persisted)->not->toBeNull()
        ->and($persisted['workflowId'])->toBe('cli_wf');
});

it('resumes a run from persisted file state without re-running completed steps', function (): void {
    $store = new FileStateStore(tempStateDir());
    [$runner, $definitions] = cliRunner($store);
    $definitionId = cliDefinitionIdOf($definitions);

    // Seed persisted state as if step one already succeeded in an earlier process.
    $store->save('cli_resume_1', [
        'definitionId' => $definitionId,
        'workflowId' => 'cli_wf',
        'steps' => ['one' => ['statusCode' => 200, 'status' => 'succeeded']],
        'inputs' => [],
        'components' => [],
    ]);

    $result = $runner->resume(cliDocument(), 'cli_resume_1');

    expect($result->status)->toBe('succeeded');
});
