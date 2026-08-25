<?php

declare(strict_types=1);

namespace Alama\Arazzo\Console\Command;

use Alama\Arazzo\Console\DocumentLoader;
use Alama\Arazzo\Renderer\Renderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'render', description: 'Render an Arazzo document as Markdown docs or a Mermaid flowchart')]
final class RenderCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to an Arazzo YAML/JSON document')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: markdown or mermaid', 'markdown')
            ->addOption('workflow', 'w', InputOption::VALUE_REQUIRED, 'Restrict Mermaid output to one workflowId')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write to file instead of stdout');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $file */
        $file = $input->getArgument('file');
        $document = DocumentLoader::load($file);

        $format = strtolower(is_string($input->getOption('format')) ? $input->getOption('format') : 'markdown');

        $renderer = new Renderer();

        $content = match ($format) {
            'markdown', 'md' => $renderer->toMarkdown($document),
            'mermaid', 'mmd' => $renderer->toMermaid(
                $document,
                ($w = $input->getOption('workflow')) === null || !is_string($w) ? null : $w,
            ),
            default => null,
        };

        if ($content === null) {
            $output->writeln("<error>unknown format</error> '{$format}' (expected markdown|mermaid)");

            return Command::FAILURE;
        }

        $outputPathRaw = $input->getOption('output');
        $outputPath = is_string($outputPathRaw) ? $outputPathRaw : null;

        if ($outputPath !== null) {
            file_put_contents($outputPath, $content);
            $output->writeln('<info>written</info> ' . $outputPath);

            return Command::SUCCESS;
        }

        $output->write($content);

        return Command::SUCCESS;
    }
}
