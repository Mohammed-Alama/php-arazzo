<?php

declare(strict_types=1);

use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;

it('autoloads the evaluation package evaluation engine')
    ->expect(new EvaluationEngine())->toBeInstanceOf(EvaluationEngineInterface::class);
