<?php

declare(strict_types=1);

arch('evaluation does not depend on illuminate framework')
    ->expect('Alama\Arazzo\Evaluation')
    ->not->toUse('Illuminate');

arch('evaluation does not leak document/runner internals')
    ->expect('Alama\Arazzo\Evaluation')
    ->not->toUse('Alama\Arazzo\Document')
    ->not->toUse('Alama\Arazzo\Runner')
    ->not->toUse('Alama\Arazzo\Cli');

arch('evaluation consumes only parser from expression')
    ->expect('Alama\Arazzo\Evaluation')
    ->not->toUse('Alama\Arazzo\Expression\Lexer')
    ->not->toUse('Alama\Arazzo\Expression\ExpressionInspector');
