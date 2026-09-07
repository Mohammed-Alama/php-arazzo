<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Bindings;

use Alama\Arazzo\Document\Document;
use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\ExpressionEngineInterface;
use Alama\Arazzo\Runner\RunnerFacade;
use Alama\Arazzo\Runner\RunnerFacadeInterface;
use Alama\Arazzo\Runner\RunnerGraphBuilder;
use Alama\Arazzo\Runner\RunnerGraphBuilderInterface;
use Illuminate\Contracts\Container\Container;
use Psr\Http\Client\ClientInterface;

/** Entry-point facade bindings: each *Interface resolves to its self-contained concrete facade. */
final class FacadeBindings
{
    public static function register(Container $app): void
    {
        $app->singleton(ExpressionEngineInterface::class, fn (): ExpressionEngine => new ExpressionEngine());
        $app->singleton(DocumentInterface::class, fn (): Document => new Document());
        $app->singleton(RunnerFacadeInterface::class, fn (): RunnerFacade => new RunnerFacade(
            $app->make(DocumentInterface::class),
            $app->make(ExpressionEngineInterface::class),
        ));
        $app->singleton(RunnerGraphBuilderInterface::class, function (Container $app): RunnerGraphBuilder {
            return new RunnerGraphBuilder(
                $app->make(DocumentInterface::class),
                $app->make(ExpressionEngineInterface::class),
                $app->bound(ClientInterface::class) ? $app->make(ClientInterface::class) : null,
            );
        });
    }
}
