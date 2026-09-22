<?php

declare(strict_types=1);

arch('evaluation does not depend on illuminate framework')
    ->expect('Alama\Arazzo\Evaluation')
    ->not->toUse('Illuminate');

arch('evaluation does not leak document/runner internals')
    ->expect('Alama\Arazzo\Evaluation')
    ->not->toUse('Alama\Arazzo\Document\Parser')
    ->not->toUse('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->not->toUse('Alama\Arazzo\Document\Validator');

arch('evaluation consumes only the parse-side seam from arazzo-expression')
    ->expect('Alama\Arazzo\Evaluation')
    ->toUse('Alama\Arazzo\Expression\Interfaces\ExpressionInterface')
    ->not->toUse('Alama\Arazzo\Expression\Lexer')
    ->not->toUse('Alama\Arazzo\Expression\ExpressionInspector');
