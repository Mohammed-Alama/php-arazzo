<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression;

use Alama\Arazzo\Expression\Exceptions\SelectorEvaluationException;
use Alama\Arazzo\Expression\Xpath\DomXpathEvaluator;

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

it('matches namespaced nodes when namespaces are registered', function () {
    $xml = '<ns:root xmlns:ns="http://example.com/ns"><ns:item>widget</ns:item></ns:root>';
    $result = (new DomXpathEvaluator())->query(
        $xml,
        '/ns:root/ns:item',
        'xpath-10',
        ['ns' => 'http://example.com/ns'],
    );
    expect($result)->toBe('widget');
});
