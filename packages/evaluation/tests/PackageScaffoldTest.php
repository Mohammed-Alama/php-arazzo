<?php

declare(strict_types=1);

use Alama\Arazzo\Evaluation\ExpressionEngine;
use Alama\Arazzo\Evaluation\ExpressionEngineInterface;

it('autoloads the evaluation package expression engine')
    ->expect(new ExpressionEngine())->toBeInstanceOf(ExpressionEngineInterface::class);
