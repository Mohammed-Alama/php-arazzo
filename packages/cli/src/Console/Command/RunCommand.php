<?php

declare(strict_types=1);

namespace Alama\Arazzo\Cli\Console\Command;

use Alama\Arazzo\Cli\Console\DocumentLoader;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Runner\RunnerFacade;
use Alama\Arazzo\Sources\Resolver\SourceRegistry;
use Alama\Arazzo\Sources\SourceGraph;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'run', description: 'Execute a workflow from an Arazzo document')]
final class RunCommand extends Command
{
    public function __construct(
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?RequestFactoryInterface $httpFactory = null,
        private readonly ?SourceRegistry $registry = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to an Arazzo YAML/JSON document')
            ->addOption('workflow', 'w', InputOption::VALUE_REQUIRED, 'workflowId to run (defaults to the first workflow)')
            ->addOption('input', 'i', InputOption::VALUE_REQUIRED, 'Workflow inputs as inline JSON or @file.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $file */
        $file = $input->getArgument('file');
        $document = DocumentLoader::load($file);
        /** @var string|null $workflowId */
        $workflowId = $input->getOption('workflow');
        $workflow = $this->findWorkflow($document, $workflowId);

        if ($workflow === null) {
            $output->writeln('<error>'.sprintf("unknown workflow '%s'", (string) $workflowId).'</error>');

            return Command::FAILURE;
        }

        /** @var string|null $rawInput */
        $rawInput = $input->getOption('input');
        $inputs = $this->parseInputs($rawInput, $output);
        if ($inputs === null) {
            return Command::FAILURE;
        }

        $registry = $this->resolveRegistry();
        $engine = new EvaluationEngine(expression: new ExpressionEngine());
        $runtime = SourceGraph::runtime(registry: $registry);
        $runner = new RunnerFacade($runtime->document, $runtime->operations, $engine, $this->httpClient);

        /** @var array<string, mixed> $inputs */
        $result = $runner->execute($document, (string) $workflow->workflowId, $inputs);

        $this->renderOutput($output, $result);

        return $result['status'] === 'succeeded' ? Command::SUCCESS : Command::FAILURE;
    }

    private function findWorkflow(ArazzoDocument $document, ?string $workflowId): ?Workflow
    {
        foreach ($document->workflows as $candidate) {
            if ($workflowId === null || $candidate->workflowId === $workflowId) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function parseInputs(?string $rawInput, OutputInterface $output): ?array
    {
        if (!is_string($rawInput) || $rawInput === '') {
            return [];
        }

        $json = str_starts_with($rawInput, '@') ? (string) file_get_contents(substr($rawInput, 1)) : $rawInput;
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            $output->writeln('<error>--input must be a JSON object or @file containing one</error>');

            return null;
        }

        return $decoded;
    }

    private function resolveRegistry(): ?SourceRegistry
    {
        if ($this->registry !== null) {
            return $this->registry;
        }

        if ($this->httpClient !== null || $this->httpFactory !== null) {
            return SourceGraph::createRegistry($this->httpClient, $this->httpFactory);
        }

        return null;
    }

    /** @param array{workflowId: string, status: string, steps: array<string, array{success: bool, stepId: string, error?: string|null}>, outputs: array<string, mixed>} $result */
    private function renderOutput(OutputInterface $output, array $result): void
    {
        $output->writeln(sprintf('workflow <info>%s</info>: <comment>%s</comment>', $result['workflowId'], $result['status']));

        foreach ($result['steps'] as $stepResult) {
            $status = $stepResult['success'] ? '<info>✔</info>' : '<error>✘</error>';
            $output->writeln(sprintf('  %s %s', $status, $stepResult['stepId']));
        }

        if ($result['outputs'] !== []) {
            $output->writeln('outputs:');
            foreach ($result['outputs'] as $name => $value) {
                $output->writeln(sprintf('  %s = %s', $name, json_encode($value)));
            }
        }
    }
}
