<?php

declare(strict_types=1);

use Alama\Arazzo\Tests\TestCase;

require_once __DIR__.'/Support/helpers.php';

require_once __DIR__.'/../../../vendor/autoload.php';

// cebe/php-openapi emits PHP 8.4 "implicitly nullable parameter"
// deprecations when its spec classes are compiled. That is vendor noise,
// so load the whole spec folder once inside a silenced window instead of
// letting every test record the same deprecation.
set_error_handler(static fn (): bool => true, E_DEPRECATED);

$cebeSrc = dirname(__DIR__, 2).'/vendor/cebe/php-openapi/src/';

foreach (array_merge(
    [$cebeSrc.'SpecBaseObject.php', $cebeSrc.'SpecObjectInterface.php', $cebeSrc.'ReferenceContext.php', $cebeSrc.'Reference.php', $cebeSrc.'Reader.php'],
    glob($cebeSrc.'spec/*.php') ?: [],
) as $cebeFile) {
    if (is_string($cebeFile) && is_file($cebeFile)) {
        require_once $cebeFile;
    }
}

restore_error_handler();

pest()->extend(TestCase::class)->in(__DIR__);
