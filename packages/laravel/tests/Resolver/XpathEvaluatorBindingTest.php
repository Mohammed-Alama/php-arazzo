<?php

declare(strict_types=1);

use Alama\Arazzo\Evaluation\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Evaluation\Xpath\XpathEvaluator;

it('binds XpathEvaluator to DomXpathEvaluator by default', function () {
    expect(app(XpathEvaluator::class))->toBeInstanceOf(DomXpathEvaluator::class);
});
