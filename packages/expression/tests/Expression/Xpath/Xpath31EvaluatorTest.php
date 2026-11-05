<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression\Xpath;

use Alama\Arazzo\Expression\Exceptions\SelectorEvaluationException;
use Alama\Arazzo\Expression\Xpath\Xpath31Evaluator;

it('advertises xpath-10 and xpath-31', function () {
    expect((new Xpath31Evaluator())->supportedVersions())->toBe(['xpath-10', 'xpath-31']);
});

it('queries XML with XPath 3.1', function () {
    $xml = '<root><item id="1">a</item><item id="2">b</item></root>';
    $result = (new Xpath31Evaluator())->query($xml, '//item[@id="2"]/text()', 'xpath-31');
    expect($result)->toBe('b');
});

it('queries XML with XPath 3.1 and namespaces', function () {
    $xml = '<ns:root xmlns:ns="http://example.com/ns"><ns:item>widget</ns:item></ns:root>';
    $result = (new Xpath31Evaluator())->query(
        $xml,
        '/ns:root/ns:item',
        'xpath-31',
        ['ns' => 'http://example.com/ns'],
    );
    expect($result)->toBe('widget');
});

it('rejects unsupported version', function () {
    (new Xpath31Evaluator())->query('<r/>', '/r', 'xpath-20');
})->throws(SelectorEvaluationException::class);

it('rejects non-XML input', function () {
    (new Xpath31Evaluator())->query(['not' => 'xml'], '/r', 'xpath-31');
})->throws(SelectorEvaluationException::class);
