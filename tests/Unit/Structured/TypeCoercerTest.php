<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Structured;

use LLMesh\Core\Structured\TypeCoercer;
use LLMesh\Core\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class CoercerTestHelper
{
    public function types(
        string $str,
        int $int,
        float $float,
        bool $bool,
        array $arr,
        \DateTimeImmutable $dateTime,
        EnumStringStatus $enum,
        ?string $nullableStr,
    ) {}
}

final class TypeCoercerTest extends TestCase
{
    private TypeCoercer $coercer;
    private array $types;

    protected function setUp(): void
    {
        $this->coercer = new TypeCoercer();
        $ref = new \ReflectionMethod(CoercerTestHelper::class, 'types');
        $this->types = [];
        foreach ($ref->getParameters() as $param) {
            $this->types[$param->getName()] = $param->getType();
        }
    }

    public function testStringCoercion(): void
    {
        $type = $this->types['str'];

        // string works
        $this->assertSame('hello', $this->coercer->coerce('hello', $type, 'str'));
        // int works
        $this->assertSame('123', $this->coercer->coerce(123, $type, 'str'));
        // float works
        $this->assertSame('1.23', $this->coercer->coerce(1.23, $type, 'str'));

        // array fails
        $this->expectException(ValidationException::class);
        $this->coercer->coerce(['abc'], $type, 'str');
    }

    public function testIntCoercion(): void
    {
        $type = $this->types['int'];

        // int works
        $this->assertSame(42, $this->coercer->coerce(42, $type, 'int'));
        // numeric string works
        $this->assertSame(42, $this->coercer->coerce('42', $type, 'int'));

        // non-numeric string fails
        $this->expectException(ValidationException::class);
        $this->coercer->coerce('abc', $type, 'int');
    }

    public function testFloatCoercion(): void
    {
        $type = $this->types['float'];

        // float works
        $this->assertSame(12.34, $this->coercer->coerce(12.34, $type, 'float'));
        // int works
        $this->assertSame(12.0, $this->coercer->coerce(12, $type, 'float'));
        // numeric string works
        $this->assertSame(12.34, $this->coercer->coerce('12.34', $type, 'float'));

        // non-numeric string fails
        $this->expectException(ValidationException::class);
        $this->coercer->coerce('abc', $type, 'float');
    }

    public function testBoolCoercion(): void
    {
        $type = $this->types['bool'];

        $this->assertTrue($this->coercer->coerce(true, $type, 'bool'));
        $this->assertTrue($this->coercer->coerce('true', $type, 'bool'));
        $this->assertTrue($this->coercer->coerce(1, $type, 'bool'));
        $this->assertTrue($this->coercer->coerce('1', $type, 'bool'));

        $this->assertFalse($this->coercer->coerce(false, $type, 'bool'));
        $this->assertFalse($this->coercer->coerce('false', $type, 'bool'));
        $this->assertFalse($this->coercer->coerce(0, $type, 'bool'));
        $this->assertFalse($this->coercer->coerce('0', $type, 'bool'));

        $this->expectException(ValidationException::class);
        $this->coercer->coerce('abc', $type, 'bool');
    }

    public function testDateTimeImmutableCoercion(): void
    {
        $type = $this->types['dateTime'];

        // ATOM format
        $val1 = '2026-06-07T09:15:00+00:00';
        $res1 = $this->coercer->coerce($val1, $type, 'dateTime');
        $this->assertInstanceOf(\DateTimeImmutable::class, $res1);
        $this->assertSame('2026-06-07T09:15:00+00:00', $res1->format(\DateTimeInterface::ATOM));

        // Y-m-d format
        $val2 = '2026-06-07';
        $res2 = $this->coercer->coerce($val2, $type, 'dateTime');
        $this->assertSame('2026-06-07', $res2->format('Y-m-d'));

        // Invalid format throws ValidationException
        $this->expectException(ValidationException::class);
        $this->coercer->coerce('invalid date', $type, 'dateTime');
    }

    public function testEnumCoercion(): void
    {
        $type = $this->types['enum'];

        $res1 = $this->coercer->coerce('open', $type, 'enum');
        $this->assertSame(EnumStringStatus::Open, $res1);

        $this->expectException(ValidationException::class);
        $this->coercer->coerce('invalid', $type, 'enum');
    }

    public function testNullableCoercion(): void
    {
        $nullableType = $this->types['nullableStr'];
        $nonNullableType = $this->types['str'];

        // nullable accepts null
        $this->assertNull($this->coercer->coerce(null, $nullableType, 'nullableStr'));

        // non-nullable throws on null
        $this->expectException(ValidationException::class);
        $this->coercer->coerce(null, $nonNullableType, 'str');
    }
}
