<?php

declare(strict_types=1);

use Alama\Arazzo\Runner\Evaluation\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Runner\Evaluation\Xpath\XpathEvaluator;

it('binds XpathEvaluator to DomXpathEvaluator by default', function () {
    expect(app(XpathEvaluator::class))->toBeInstanceOf(DomXpathEvaluator::class);
});
