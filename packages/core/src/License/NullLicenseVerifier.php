<?php
declare(strict_types=1);
namespace Alama\Arazzo\License;
use DateTimeImmutable;

final class NullLicenseVerifier implements LicenseVerifierInterface
{
    public function verifyOrThrow(string $feature): void
    {
    }
    public function isValid(string $feature): bool
    {
        return true;
    }
    public function expiresAt(string $feature): ?DateTimeImmutable
    {
        return null;
    }
}
