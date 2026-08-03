<?php
declare(strict_types=1);
namespace Alama\Arazzo\Tests\License;
use Alama\Arazzo\License\LicenseVerifierInterface;
use Alama\Arazzo\License\NullLicenseVerifier;

it('reports every feature as valid', function (): void {
    $verifier = new NullLicenseVerifier();
    expect($verifier->isValid('persistence'))->toBeTrue()
        ->and($verifier->isValid('saga'))->toBeTrue()
        ->and($verifier->isValid('anything-at-all'))->toBeTrue();
});

it('never throws from verifyOrThrow', function (): void {
    $verifier = new NullLicenseVerifier();
    $verifier->verifyOrThrow('persistence');
    $verifier->verifyOrThrow('multitenancy');
    expect(true)->toBeTrue();
});

it('returns null expiry for any feature', function (): void {
    $verifier = new NullLicenseVerifier();
    expect($verifier->expiresAt('persistence'))->toBeNull();
});

it('implements the LicenseVerifierInterface contract', function (): void {
    expect(new NullLicenseVerifier())->toBeInstanceOf(LicenseVerifierInterface::class);
});
