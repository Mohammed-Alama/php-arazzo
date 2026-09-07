<?php

declare(strict_types=1);

namespace Alama\Arazzo\Cli\Console\Command;

use Alama\Arazzo\Cli\Console\DocumentLoader;
use Alama\Arazzo\Document\Document;
use Alama\Arazzo\Document\Resolver\SourceRegistry;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Runner\RunnerFacade;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
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

        $workflow = null;

        foreach ($document->workflows as $candidate) {
            if ($workflowId === null || $candidate->workflowId === $workflowId) {
                $workflow = $candidate;
                break;
            }
        }

        if ($workflow === null) {
            $output->writeln('<error>'.sprintf("unknown workflow '%s'", (string) $workflowId).'</error>');

            return Command::FAILURE;
        }

        /** @var string|null $rawInput */
        $rawInput = $input->getOption('input');
        $inputs = [];

        if (is_string($rawInput) && $rawInput !== '') {
            $json = str_starts_with($rawInput, '@') ? (string) file_get_contents(substr($rawInput, 1)) : $rawInput;
            $decoded = json_decode($json, true);

            if (!is_array($decoded)) {
                $output->writeln('<error>--input must be a JSON object or @file containing one</error>');

                return Command::FAILURE;
            }

            $inputs = $decoded;
        }

        $client = $this->httpClient ?? new Client();
        $factory = new HttpFactory();

        $engine = new ExpressionEngine();
        $documents = new Document($client, $factory, $this->registry);

        $runner = new RunnerFacade($documents, $engine, $this->httpClient);

        /** @var array<string, mixed> $inputs */
        $result = $runner->execute($document, (string) $workflow->workflowId, $inputs);

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

        return $result['status'] === 'succeeded' ? Command::SUCCESS : Command::FAILURE;
    }
}
