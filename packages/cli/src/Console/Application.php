<?php

declare(strict_types=1);

namespace Alama\Arazzo\Console;

use Alama\Arazzo\Console\Command\ExplainCommand;
use Alama\Arazzo\Console\Command\ListWorkflowsCommand;
use Alama\Arazzo\Console\Command\RenderCommand;
use Alama\Arazzo\Console\Command\RunCommand;
use Alama\Arazzo\Console\Command\ValidateCommand;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputOption;

final class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('arazzo', '1.0.0-alpha');

        $this->getDefinition()->addOptions([
            new InputOption('--working-dir', '-d', InputOption::VALUE_REQUIRED, 'If specified, use the given directory as working directory.'),
        ]);

        $this->addCommands([
            new ValidateCommand(),
            new ListWorkflowsCommand(),
            new ExplainCommand(),
            new RunCommand(),
            new RenderCommand(),
        ]);
    }
}
