<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\TypeCaster;
use PHPUnit\Framework\TestCase;

class TypeCasterTest extends TestCase
{
    public function test_casts_to_integer(): void
    {
        $this->assertSame(42, TypeCaster::asInteger('42'));
        $this->assertSame(42, TypeCaster::asInteger(42));
    }

    public function test_throws_on_invalid_integer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TypeCaster::asInteger(['array']);
    }

    public function test_casts_to_string(): void
    {
        $this->assertSame('42', TypeCaster::asString(42));
        $this->assertSame('true', TypeCaster::asString(true));
    }

    public function test_casts_to_array(): void
    {
        $this->assertSame(['a'], TypeCaster::asArray(['a']));
        $this->assertSame(['a'], TypeCaster::asArray('a'));
    }
}
