<?php
declare(strict_types=1);
namespace Alama\Arazzo\License;
use DateTimeImmutable;

interface LicenseVerifierInterface
{
    public function verifyOrThrow(string $feature): void;
    public function isValid(string $feature): bool;
    public function expiresAt(string $feature): ?DateTimeImmutable;
}
