<?php

declare(strict_types=1);

namespace Alama\Arazzo\Console\Command;

use Alama\Arazzo\Console\DocumentLoader;
use Alama\Arazzo\Parser\Exceptions\LoaderException;
use Alama\Arazzo\Parser\Exceptions\ParserException;
use Alama\Arazzo\Validator\RuleSet;
use Alama\Arazzo\Validator\Validator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'validate', description: 'Parse and validate an Arazzo document')]
final class ValidateCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to an Arazzo YAML/JSON document');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $file */
        $file = $input->getArgument('file');

        try {
            $document = DocumentLoader::load($file);
        } catch (LoaderException|ParserException $e) {
            $output->writeln('<error>PARSE ERROR</error> ' . $e->getMessage());

            return Command::FAILURE;
        }

        $result = (new Validator(RuleSet::default()))->validate($document);

        if ($result->isValid()) {
            $output->writeln('<info>✔ valid</info> ' . $file);

            return Command::SUCCESS;
        }

        $output->writeln('<error>✘ invalid</error> ' . $file);

        foreach ($result->errors as $error) {
            $output->writeln(sprintf(
                '  <fg=red>[%s]</> %s <comment>%s</comment>',
                $error->code,
                $error->message,
                $error->path,
            ));
        }

        foreach ($result->warnings as $warning) {
            $output->writeln(sprintf(
                '  <fg=yellow>[%s]</> %s <comment>%s</comment>',
                $warning->code,
                $warning->message,
                $warning->path,
            ));
        }

        return Command::FAILURE;
    }
}
