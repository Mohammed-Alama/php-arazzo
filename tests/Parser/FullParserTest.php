<?php
declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Parser;

use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\Action\SuccessGotoAction;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Loader\Loader;
use Alama\LaravelArazzo\Loader\NativeJsonDecoder;
use Alama\LaravelArazzo\Loader\SymfonyYamlDecoder;
use Alama\LaravelArazzo\Parser\Parser;

function parseFixture(string $rel): \Alama\LaravelArazzo\Dto\ArazzoDocument {
    $loader = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
    $raw = $loader->load(__DIR__ . '/../fixtures/parser/' . $rel);
    return (new Parser())->parse($raw);
}

it('parses a full arazzo document', function (): void {
    $doc = parseFixture('full.yaml');

    expect($doc->arazzo)->toBe('1.0.0')
        ->and($doc->info->title)->toBe('Full')
        ->and($doc->sourceDescriptions[0]->type)->toBe(SourceType::Openapi)
        ->and($doc->workflows)->toHaveCount(1);

    $wf = $doc->workflows[0];
    expect($wf->workflowId)->toBe('main')
        ->and($wf->steps)->toHaveCount(2)
        ->and($wf->parameters[0]->name)->toBe('X-Trace')
        ->and($wf->outputs)->toHaveKey('user');

    $s1 = $wf->steps[0];
    expect($s1->onSuccess[0])->toBeInstanceOf(SuccessGotoAction::class)
        ->and($s1->onFailure[0])->toBeInstanceOf(RetryAction::class);

    expect($doc->components->successActions)->toHaveKey('goEnd')
        ->and($doc->specificationExtensions)->toHaveKey('x-vendor-custom');
});
