<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Resolution\Xpath\DomXpathEvaluator;
use Alama\LaravelArazzo\Resolution\Xpath\XpathEvaluator;

it('binds XpathEvaluator to DomXpathEvaluator by default', function () {
    expect(app(XpathEvaluator::class))->toBeInstanceOf(DomXpathEvaluator::class);
});
