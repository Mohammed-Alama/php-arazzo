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

arch('expression does not use evaluation-side classes')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Alama\Arazzo\Evaluation\ExpressionEngine')
    ->not->toUse('Alama\Arazzo\Evaluation\ExpressionEngineInterface')
    ->not->toUse('Alama\Arazzo\Evaluation\ExpressionEvaluator')
    ->not->toUse('Alama\Arazzo\Evaluation\SelectorEvaluator')
    ->not->toUse('Alama\Arazzo\Evaluation\StringInterpolator')
    ->not->toUse('Alama\Arazzo\Evaluation\JsonPointer')
    ->not->toUse('Alama\Arazzo\Evaluation\JsonPathEvaluator')
    ->not->toUse('Alama\Arazzo\Evaluation\Xpath');

arch('expression facade seams are entry-point only')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Alama\Arazzo\Expression\ExpressionEngine')
    ->not->toUse('Alama\Arazzo\Document\Document')
    ->not->toUse('Alama\Arazzo\Runner\RunnerFacade');
