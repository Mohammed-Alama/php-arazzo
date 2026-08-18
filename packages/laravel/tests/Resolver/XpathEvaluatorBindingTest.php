<?php

declare(strict_types=1);

use Alama\Arazzo\Resolver\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Resolver\Xpath\XpathEvaluator;

it('binds XpathEvaluator to DomXpathEvaluator by default', function () {
    expect(app(XpathEvaluator::class))->toBeInstanceOf(DomXpathEvaluator::class);
});
