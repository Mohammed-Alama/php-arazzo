<?php

declare(strict_types=1);

use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\ExpressionEngine;

it('autoloads the evaluation package evaluation engine')
    ->expect(new EvaluationEngine(expression: new ExpressionEngine()))->toBeInstanceOf(EvaluationEngineInterface::class);
