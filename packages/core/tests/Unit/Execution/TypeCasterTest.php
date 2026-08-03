<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\Arazzo\Execution\TypeCaster;
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

    public function test_casts_to_float(): void
    {
        $this->assertSame(4.2, TypeCaster::asFloat('4.2'));
        $this->assertSame(42.0, TypeCaster::asFloat(42));
    }

    public function test_throws_on_invalid_float(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TypeCaster::asFloat('not-a-number');
    }

    public function test_casts_to_boolean(): void
    {
        $this->assertTrue(TypeCaster::asBoolean(true));
        $this->assertTrue(TypeCaster::asBoolean('true'));
        $this->assertFalse(TypeCaster::asBoolean('false'));
        $this->assertTrue(TypeCaster::asBoolean(1));
        $this->assertFalse(TypeCaster::asBoolean(0));
    }

    public function test_throws_on_invalid_boolean(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TypeCaster::asBoolean('not-a-bool');
    }
}
