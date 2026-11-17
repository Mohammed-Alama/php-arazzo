<?php

declare(strict_types=1);

use Alama\Arazzo\Tests\Conformance\ConformanceManifestBuilder;
use Alama\Arazzo\Tests\Conformance\OaiCorpusRunner;

$documents = OaiCorpusRunner::documents();
$model = ConformanceManifestBuilder::build($documents);
$capabilities = $model['capabilities'];
$capabilityById = array_column($capabilities, null, 'id');

const EXPECTED_CAPABILITIES = [
    'parse-and-validate',
    'execute-sync',
    'execute-queued',
    'source-resolution',
];

const VOCABULARY = [
    'supported',
    'partial',
    'unsupported',
    'not-applicable',
];

test('manifest exposes the required top-level sections', function () use ($model) {
    expect(array_keys($model))->toBe([
        'corpus',
        'statusVocabulary',
        'capabilities',
        'documents',
    ]);
});

test('corpus section names the OAI example corpus', function () use ($model, $documents) {
    expect($model['corpus'])->toHaveKeys(['title', 'sourceUrl', 'snapshotDir', 'documentCount']);
    expect($model['corpus']['title'])->toBe('OAI Arazzo examples');
    expect($model['corpus']['documentCount'])->toBe(count($documents));
});

test('status vocabulary is the sanctioned four values in order', function () use ($model) {
    expect($model['statusVocabulary'])->toBe(VOCABULARY);
});

test('every capability carries id, name, description, status and evidence', function () use ($capabilities) {
    foreach ($capabilities as $capability) {
        expect($capability)->toHaveKeys(['id', 'name', 'description', 'status', 'evidence']);
        expect(in_array($capability['status'], VOCABULARY, true))->toBeTrue(
            "unexpected status '{$capability['status']}' for '{$capability['id']}'",
        );
        expect(is_array($capability['evidence']))->toBeTrue();

        foreach ($capability['evidence'] as $evidence) {
            expect($evidence)->toHaveKeys(['document', 'result', 'ok']);
            expect(is_bool($evidence['ok']))->toBeTrue();
            expect(is_string($evidence['document']))->toBeTrue();
            expect(is_string($evidence['result']))->toBeTrue();
        }
    }
});

test('manifest declares the minimum set of capabilities', function () use ($capabilityById) {
    foreach (EXPECTED_CAPABILITIES as $id) {
        expect(array_key_exists($id, $capabilityById))->toBeTrue("missing capability '{$id}'");
    }
});

test('supported status always requires at least one evidence entry', function () use ($capabilities) {
    foreach ($capabilities as $capability) {
        if ($capability['status'] === 'supported') {
            expect(count($capability['evidence']))->toBeGreaterThan(0);
        }
    }
});

test('parse-and-validate is supported across the whole corpus', function () use ($capabilityById, $documents) {
    $cap = $capabilityById['parse-and-validate'];

    expect($cap['status'])->toBe('supported');
    expect(count($cap['evidence']))->toBe(count($documents));

    foreach ($cap['evidence'] as $evidence) {
        expect($evidence['ok'])->toBeTrue("'{$evidence['document']}' did not pass Tier 1");
        expect($evidence['result'])->toBe('pass');
    }
});

test('execute-sync is supported via bnpl-arazzo', function () use ($capabilityById) {
    $cap = $capabilityById['execute-sync'];

    expect($cap['status'])->toBe('supported');
    expect(count($cap['evidence']))->toBe(1);
    expect($cap['evidence'][0]['document'])->toBe('bnpl-arazzo');
    expect($cap['evidence'][0]['result'])->toBe('succeeded');
    expect($cap['evidence'][0]['ok'])->toBeTrue();
});

test('execute-queued is supported via the sub-workflow documents', function () use ($capabilityById) {
    $cap = $capabilityById['execute-queued'];

    expect($cap['status'])->toBe('supported');
    expect(array_column($cap['evidence'], 'document'))->toBe(['oauth', 'pet-coupons']);

    foreach ($cap['evidence'] as $evidence) {
        expect($evidence['result'])->toBe('succeeded');
        expect($evidence['ok'])->toBeTrue();
    }
});

test('source-resolution is partial because one companion is missing upstream', function () use ($capabilityById, $documents) {
    $cap = $capabilityById['source-resolution'];

    expect($cap['status'])->toBe('partial');
    expect(count($cap['evidence']))->toBe(count($documents));

    $byDocument = array_column($cap['evidence'], null, 'document');
    expect($byDocument['ExtendedParametersExample']['ok'])->toBeFalse();
    expect($byDocument['ExtendedParametersExample']['result'])->toBe('missing-local-companion');

    foreach (['bnpl-arazzo', 'FAPI-PAR', 'LoginAndRetrievePets', 'oauth', 'pet-coupons'] as $document) {
        expect($byDocument[$document]['ok'])->toBeTrue("'$document' source should resolve");
        expect($byDocument[$document]['result'])->toBe('resolved');
    }
});

test('document rows cover every corpus document, sorted by name', function () use ($model, $documents) {
    expect(array_column($model['documents'], 'name'))->toBe(array_keys($documents));

    foreach ($model['documents'] as $row) {
        expect($row)->toHaveKeys(['name', 'version', 'parseValidate', 'adapter', 'execute', 'notes']);
        expect(is_array($row['notes']))->toBeTrue();
    }
});

test('manifest is deterministic across repeated builds', function () use ($documents) {
    $first = ConformanceManifestBuilder::serialize(ConformanceManifestBuilder::build($documents));
    $second = ConformanceManifestBuilder::serialize(ConformanceManifestBuilder::build($documents));

    expect($first)->toBe($second);
});

test('serialized JSON is stable under decode and re-encode (deterministic key ordering)', function () use ($model) {
    $decoded = json_decode(ConformanceManifestBuilder::serialize($model), true);

    expect(ConformanceManifestBuilder::serialize($decoded))->toBe(ConformanceManifestBuilder::serialize($model));
});
