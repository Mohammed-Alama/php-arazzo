<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Soap\Resolver;

use Alama\Arazzo\Protocol\Soap\Resolver\XPathReplacementTargetResolver;
use Alama\Arazzo\Protocol\Soap\Transfer\SoapResponseTransfer;
use DOMDocument;

function xpathResolverDom(string $xml): DOMDocument
{
    $dom = new DOMDocument();
    $dom->loadXML($xml);

    return $dom;
}

it('advertises xpath support only', function (): void {
    $resolver = new XPathReplacementTargetResolver();

    expect($resolver->name())->toBe('xpath-replacement');
    expect($resolver->priority())->toBe(200);
    expect($resolver->supports('xpath'))->toBeTrue();
    expect($resolver->supports('jsonpath'))->toBeFalse();
    expect($resolver->supports('json-pointer'))->toBeFalse();
    expect($resolver->supports('proto-field'))->toBeFalse();
});

it('writes the replacement value into a namespaced envelope via a prefixed path', function (): void {
    $dom = xpathResolverDom(
        '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
        .'<soapenv:Body><AddResponse>8</AddResponse></soapenv:Body></soapenv:Envelope>',
    );
    $transfer = new SoapResponseTransfer(status: 200, headers: [], rawBody: '', views: ['xml' => $dom]);
    $resolver = new XPathReplacementTargetResolver();

    $result = $resolver->resolve($transfer, 'xpath:/soapenv:Envelope/soapenv:Body/AddResponse', 9);

    expect($result)->toBe(1);
    expect($transfer->view('xml')->getElementsByTagName('AddResponse')->item(0)->textContent)->toBe('9');
});

it('writes into a namespace-free document with a bare xpath target', function (): void {
    $dom = xpathResolverDom('<Envelope><Body><Fault><faultcode>Server</faultcode></Fault></Body></Envelope>');
    $resolver = new XPathReplacementTargetResolver();

    $result = $resolver->resolve($dom, '/Envelope/Body/Fault/faultcode', 'Client');

    expect($result)->toBe(1);
    expect($dom->getElementsByTagName('faultcode')->item(0)->textContent)->toBe('Client');
});

it('touches every node matched by the xpath', function (): void {
    $dom = xpathResolverDom('<responses><value>1</value><value>2</value></responses>');
    $resolver = new XPathReplacementTargetResolver();

    $result = $resolver->resolve($dom, '//value', 0);

    expect($result)->toBe(2);
    expect($dom->getElementsByTagName('value')->item(0)->textContent)->toBe('0');
    expect($dom->getElementsByTagName('value')->item(1)->textContent)->toBe('0');
});

it('returns null for containers without a DOM or xml view', function (): void {
    $resolver = new XPathReplacementTargetResolver();

    expect($resolver->resolve('not-a-dom', '/Envelope', 'x'))->toBeNull();
    expect($resolver->resolve(new SoapResponseTransfer(status: 200, headers: [], rawBody: ''), '/Envelope', 'x'))->toBeNull();
});

it('returns zero when the xpath matches nothing', function (): void {
    $dom = xpathResolverDom('<Envelope><Body/></Envelope>');
    $resolver = new XPathReplacementTargetResolver();

    expect($resolver->resolve($dom, '/Envelope/Missing', 'x'))->toBe(0);
});

it('returns null for an invalid xpath expression', function (): void {
    $dom = xpathResolverDom('<Envelope/>');
    $resolver = new XPathReplacementTargetResolver();

    expect($resolver->resolve($dom, '/Envelope[', 'x'))->toBeNull();
});
