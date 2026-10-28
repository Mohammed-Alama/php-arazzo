<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression\Support\Abnf;

/**
 * @internal stays out of the advertised contract; test support only, never shipped.
 */
final class AbnfRule
{
    public function __construct(
        public string $name,
        /** @var list<mixed> */
        public array $elements,
    ) {}
}
