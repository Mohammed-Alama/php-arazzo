<?php

declare(strict_types=1);

namespace Alama\Arazzo\Console\Command;

use Alama\Arazzo\Console\DocumentLoader;
use Alama\Arazzo\Runner\Evaluation\DependencyGraph;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'explain', description: 'Dump the dependency graph / execution order of a workflow')]
final class ExplainCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to an Arazzo YAML/JSON document')
            ->addOption('workflow', 'w', InputOption::VALUE_REQUIRED, 'workflowId (defaults to the first workflow)');
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

        $graph = new DependencyGraph($workflow->steps);
        $order = $graph->getTopologicalOrder();

        $output->writeln(sprintf('workflow <info>%s</info>: %d step(s)', $workflow->workflowId, count($workflow->steps)));
        $output->writeln('execution order (topological):');

        $position = 0;

        foreach ($order as $stepId) {
            $position++;
            $deps = $graph->getEffectiveDependencies($stepId);
            $output->writeln(sprintf(
                '  %d. <info>%s</info>%s',
                $position,
                $stepId,
                $deps === [] ? '' : '  after '.implode(', ', $deps),
            ));
        }

        if (($cycle = $graph->getCycle()) !== null) {
            $output->writeln('<error>cycle detected:</error> '.implode(' -> ', $cycle));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
