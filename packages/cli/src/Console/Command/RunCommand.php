<?php

declare(strict_types=1);

namespace Alama\Arazzo\Cli\Console\Command;

use Alama\Arazzo\Cli\Console\DocumentLoader;
use Alama\Arazzo\Document\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Document\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Document\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Document\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Document\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Document\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Document\Resolver\Fetchers\HttpFetcher;
use Alama\Arazzo\Document\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Document\Resolver\SourceRegistry;
use Alama\Arazzo\Expression\Evaluation\CriteriaEvaluator;
use Alama\Arazzo\Expression\Evaluation\ExpressionResolver;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Runner\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Runner\Execution\ResponseSchemaValidator;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\StepOutputExtractor;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
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
        $httpFetcher = new HttpFetcher($client, $factory);

        $registry = $this->registry ?? new SourceRegistry(new DefaultSourceResolver([
            'http' => $httpFetcher,
            'https' => $httpFetcher,
            'file' => new LocalFetcher(),
        ]));

        $evaluator = new ExpressionEvaluator();
        $operationResolver = new OpenApiOperationResolver(
            new OpenApiDocumentLoader($registry),
            new OpenApiVersionDetector(),
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        );
        $resolver = new ExpressionResolver(
            $evaluator,
            new StepOutputExtractor($operationResolver, $evaluator),
            new CriteriaEvaluator($evaluator),
            new ResponseSchemaValidator($operationResolver),
        );

        $executor = new WorkflowExecutor(
            new StepExecutor(
                new DefaultOpenApiExecutor($client, $factory),
                $resolver,
                $operationResolver,
            ),
            workflowEngine: new WorkflowEngine($resolver),
        );

        /** @var array<string, mixed> $inputs */
        $result = $executor->execute($workflow, $document, $inputs);

        $output->writeln(sprintf('workflow <info>%s</info>: <comment>%s</comment>', $result->workflowId, $result->status));

        foreach ($result->stepResults as $stepId => $stepResult) {
            $status = $stepResult->success ? '<info>✔</info>' : '<error>✘</error>';
            $output->writeln(sprintf('  %s %s', $status, $stepId));
        }

        if ($result->outputs !== []) {
            $output->writeln('outputs:');
            foreach ($result->outputs as $name => $value) {
                $output->writeln(sprintf('  %s = %s', $name, json_encode($value)));
            }
        }

        return $result->status === 'succeeded' ? Command::SUCCESS : Command::FAILURE;
    }
}
