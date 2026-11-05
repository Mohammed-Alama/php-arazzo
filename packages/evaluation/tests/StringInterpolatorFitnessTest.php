<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Evaluation;

/** @internal stays out of the advertised contract; not part of the public API surface */
it('string interpolator has no preg_replace_callback', function (): void {
    $source = file_get_contents(__DIR__.'/../src/StringInterpolator.php');

    expect(str_contains($source, 'preg_replace_callback'))->toBeFalse();
});
