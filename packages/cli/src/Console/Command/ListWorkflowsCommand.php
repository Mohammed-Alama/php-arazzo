<?php

declare(strict_types=1);

namespace Alama\Arazzo\Cli\Console\Command;

use Alama\Arazzo\Cli\Console\DocumentLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'list-workflows', description: 'List the workflows defined in an Arazzo document')]
final class ListWorkflowsCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to an Arazzo YAML/JSON document');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $file */
        $file = $input->getArgument('file');
        $document = DocumentLoader::load($file);

        if ($document->workflows === []) {
            $output->writeln('<comment>no workflows</comment>');

            return Command::SUCCESS;
        }

        foreach ($document->workflows as $workflow) {

            $output->writeln(sprintf(
                '<info>%s</info> %s <comment>(%d steps)</comment>',
                $workflow->workflowId,
                $workflow->summary ?? '',
                count($workflow->steps),
            ));

            foreach ($workflow->steps as $step) {
                $target = $step->operationId
                    ?? $step->operationPath
                    ?? ($step->workflowId !== null ? "→ {$step->workflowId}" : ($step->action !== null ? "[{$step->action}]" : '?'));
                $output->writeln(sprintf('  - %s %s', $step->stepId, (string) $target));
            }
        }

        return Command::SUCCESS;
    }
}
