<?php

declare(strict_types=1);

use Ecosystem\RefResolver;
use Ecosystem\RelevanceMapper;
use Ecosystem\TopicMatcher;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/TopicMatcher.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/RelevanceMapper.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/RefResolver.php';

function ref_resolver(): RefResolver
{
    return new RefResolver(dirname(__DIR__, 4));
}

it('A8: resolves plan/spec refs to superpowers docs and optional headings', function (): void {
    $resolver = ref_resolver();

    expect($resolver->resolve('plan:phase-f-protocol-packages')['ok'])->toBeTrue()
        ->and($resolver->resolve('spec:plugin-stack-oms-multiprotocol')['ok'])->toBeTrue()
        ->and($resolver->resolve('spec:plugin-stack-oms-multiprotocol#goals')['ok'])->toBeTrue()
        ->and($resolver->resolve('plan:phase-f-protocol-packages')['target'])
        ->toEndWith('docs/superpowers/plans/2026-09-08-phase-f-protocol-packages.md');
});

it('A8: fails unknown schemes, missing docs and missing headings', function (): void {
    $resolver = ref_resolver();

    expect($resolver->resolve('roadmap:P0-4')['ok'])->toBeFalse()
        ->and($resolver->resolve('plan:does-not-exist')['ok'])->toBeFalse()
        ->and($resolver->resolve('spec:plugin-stack-oms-multiprotocol#not-a-heading')['ok'])->toBeFalse()
        ->and($resolver->resolve('upstream:not-a-ref')['ok'])->toBeFalse()
        ->and($resolver->resolve('upstream:OAI/Arazzo-Specification#410')['ok'])->toBeTrue();
});

it('A8: every ref that can appear in roadmapRefs resolves, and none is a roadmap:Pn-m key', function (): void {
    $resolver = ref_resolver();
    $refs = RelevanceMapper::refs();

    expect($refs)->not->toBeEmpty();

    foreach ($refs as $ref) {
        expect($ref)->not->toStartWith('roadmap:');
        $result = $resolver->resolve($ref);
        expect($result['ok'])->toBeTrue(sprintf('unresolved ref %s: %s', $ref, (string) $result['error']));
    }
});

it('A8: the authored topic refs are exactly the mapper refs', function (): void {
    $config = TopicMatcher::fromDefault()->config();
    $topicRefs = [];
    foreach ($config['topics'] as $spec) {
        foreach ((array) ($spec['roadmap'] ?? []) as $ref) {
            if (is_string($ref) && $ref !== '') {
                $topicRefs[$ref] = true;
            }
        }
    }
    $topicRefs = array_keys($topicRefs);
    sort($topicRefs);

    $mapperRefs = RelevanceMapper::refs();
    sort($mapperRefs);

    expect($topicRefs)->toBe($mapperRefs);
});
