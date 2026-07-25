<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Exceptions\SelectorEvaluationException;
use Alama\LaravelArazzo\Resolution\Xpath\DomXpathEvaluator;

it('advertises xpath-10 only', function () {
    expect((new DomXpathEvaluator())->supportedVersions())->toBe(['xpath-10']);
});

it('queries XML with XPath 1.0', function () {
    $xml = '<root><item id="1">a</item><item id="2">b</item></root>';
    $result = (new DomXpathEvaluator())->query($xml, '//item[@id="2"]/text()', 'xpath-10');
    expect($result)->toBe('b');
});

it('rejects unsupported version', function () {
    (new DomXpathEvaluator())->query('<r/>', '/r', 'xpath-30');
})->throws(SelectorEvaluationException::class);

it('rejects non-XML input', function () {
    (new DomXpathEvaluator())->query(['not' => 'xml'], '/r', 'xpath-10');
})->throws(SelectorEvaluationException::class);
