<?php

declare(strict_types=1);

arch('expression does not depend on illuminate framework')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Illuminate');

arch('expression does not leak document/runner internals')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Alama\Arazzo\Document\Parser')
    ->not->toUse('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->not->toUse('Alama\Arazzo\Document\Validator');

arch('expression does not use evaluation classes')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Alama\Arazzo\Evaluation');

arch('expression facade seams are entry-point only')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Alama\Arazzo\Sources\Document')
    ->not->toUse('Alama\Arazzo\Runner\RunnerFacade');

arch('ExpressionDependencyProvider and concrete SelectorPluginRegistry stay inside packages/expression')
    ->expect('Alama\Arazzo\Document')
    ->not->toUse('Alama\Arazzo\Expression\ExpressionDependencyProvider')
    ->not->toUse('Alama\Arazzo\Expression\Registries\SelectorPluginRegistry');

arch('ExpressionDependencyProvider and concrete SelectorPluginRegistry stay inside packages/expression (evaluation)')
    ->expect('Alama\Arazzo\Evaluation')
    ->not->toUse('Alama\Arazzo\Expression\ExpressionDependencyProvider')
    ->not->toUse('Alama\Arazzo\Expression\Registries\SelectorPluginRegistry');

arch('ExpressionDependencyProvider and concrete SelectorPluginRegistry stay inside packages/expression (laravel)')
    ->expect('Alama\Arazzo\Laravel')
    ->not->toUse('Alama\Arazzo\Expression\ExpressionDependencyProvider')
    ->not->toUse('Alama\Arazzo\Expression\Registries\SelectorPluginRegistry');

arch('ExpressionDependencyProvider and concrete SelectorPluginRegistry stay inside packages/expression (cli)')
    ->expect('Alama\Arazzo\Cli')
    ->not->toUse('Alama\Arazzo\Expression\ExpressionDependencyProvider')
    ->not->toUse('Alama\Arazzo\Expression\Registries\SelectorPluginRegistry');

arch('ExpressionDependencyProvider and concrete SelectorPluginRegistry stay inside packages/expression (sources)')
    ->expect('Alama\Arazzo\Sources')
    ->not->toUse('Alama\Arazzo\Expression\ExpressionDependencyProvider')
    ->not->toUse('Alama\Arazzo\Expression\Registries\SelectorPluginRegistry');
