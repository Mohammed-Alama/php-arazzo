<?php

declare(strict_types=1);

arch('document does not depend on illuminate framework')
    ->expect('Alama\Arazzo\Document\Parser')
    ->not->toUse('Illuminate');

arch('document does not leak runner internals')
    ->expect('Alama\Arazzo\Document\Parser')
    ->not->toUse('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->not->toUse('Alama\Arazzo\Runner\Events')
    ->not->toUse('Alama\Arazzo\Runner\Protocol')
    ->expect('Alama\Arazzo\Document\Resolver')
    ->not->toUse('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->expect('Alama\Arazzo\Document\Normalizer')
    ->not->toUse('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->expect('Alama\Arazzo\Document\Validator')
    ->not->toUse('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->not->toUse('Alama\Arazzo\Runner\Events');

arch('document does not depend on evaluation package')
    ->expect('Alama\Arazzo\Document')
    ->not->toUse('Alama\Arazzo\Evaluation');

arch('document consumes expression engine interface')
    ->expect('Alama\Arazzo\Document\Validator\Rules')
    ->toUse('Alama\Arazzo\Expression\ExpressionEngineInterface')
    ->not->toUse('Alama\Arazzo\Expression\Lexer')
    ->not->toUse('Alama\Arazzo\Expression\Parser');

arch('document facade seams are entry-point only')
    ->expect('Alama\Arazzo\Document\Parser')
    ->not->toUse('Alama\Arazzo\Document\Document')
    ->not->toUse('Alama\Arazzo\Runner\RunnerFacade')
    ->not->toUse('Alama\Arazzo\Expression\Expression');
