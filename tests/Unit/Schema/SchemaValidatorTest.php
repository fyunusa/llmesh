<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Schema;

use LLMesh\Core\Schema\Schema;
use LLMesh\Core\Schema\SchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\Schema\SchemaValidator
 */
final class SchemaValidatorTest extends TestCase
{
    private SchemaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SchemaValidator();
    }

    public function testValidatesTypesCorrectly(): void
    {
        $stringSchema = Schema::string()->toArray();
        $this->assertEmpty($this->validator->validate('hello', $stringSchema));
        $this->assertNotEmpty($this->validator->validate(123, $stringSchema));

        $intSchema = Schema::integer()->toArray();
        $this->assertEmpty($this->validator->validate(123, $intSchema));
        $this->assertNotEmpty($this->validator->validate('123', $intSchema));

        $numberSchema = Schema::number()->toArray();
        $this->assertEmpty($this->validator->validate(12.3, $numberSchema));
        $this->assertEmpty($this->validator->validate(12, $numberSchema));
        $this->assertNotEmpty($this->validator->validate(false, $numberSchema));

        $boolSchema = Schema::boolean()->toArray();
        $this->assertEmpty($this->validator->validate(true, $boolSchema));
        $this->assertNotEmpty($this->validator->validate(1, $boolSchema));
    }

    public function testValidatesEnumCorrectly(): void
    {
        $schema = Schema::enum(['active', 'inactive'])->toArray();
        $this->assertEmpty($this->validator->validate('active', $schema));
        $this->assertNotEmpty($this->validator->validate('pending', $schema));
    }

    public function testValidatesStringConstraintsCorrectly(): void
    {
        $schema = Schema::string()->minLength(3)->maxLength(5)->toArray();
        $this->assertEmpty($this->validator->validate('abc', $schema));
        $this->assertEmpty($this->validator->validate('abcde', $schema));
        $this->assertNotEmpty($this->validator->validate('ab', $schema));
        $this->assertNotEmpty($this->validator->validate('abcdef', $schema));
    }

    public function testValidatesNumericConstraintsCorrectly(): void
    {
        $schema = Schema::integer()->minimum(10)->maximum(20)->toArray();
        $this->assertEmpty($this->validator->validate(10, $schema));
        $this->assertEmpty($this->validator->validate(15, $schema));
        $this->assertNotEmpty($this->validator->validate(9, $schema));
        $this->assertNotEmpty($this->validator->validate(21, $schema));
    }

    public function testValidatesObjectRequiredPropertiesCorrectly(): void
    {
        $schema = Schema::object(
            [
            'name' => Schema::string()->required(),
            'age' => Schema::integer(),
            ]
        )->toArray();

        $this->assertEmpty($this->validator->validate(['name' => 'John', 'age' => 30], $schema));
        $this->assertEmpty($this->validator->validate(['name' => 'John'], $schema));

        $errors = $this->validator->validate(['age' => 30], $schema);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('missing required property "name"', $errors[0]);
    }

    public function testValidatesArrayCorrectly(): void
    {
        $schema = Schema::array(Schema::integer())->toArray();
        $this->assertEmpty($this->validator->validate([1, 2, 3], $schema));

        $errors = $this->validator->validate([1, 'two', 3], $schema);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('expected type "integer"', $errors[0]);
    }

    public function testValidatesNullableCorrectly(): void
    {
        $schema = Schema::string()->nullable()->toArray();
        $this->assertEmpty($this->validator->validate('hello', $schema));
        $this->assertEmpty($this->validator->validate(null, $schema));

        $errors = $this->validator->validate(123, $schema);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('does not match any of the allowed schemas', $errors[0]);
    }

    public function testValidatesFormatEmailCorrectly(): void
    {
        $schema = Schema::string()->format('email')->toArray();
        $this->assertEmpty($this->validator->validate('test@example.com', $schema));

        $errors = $this->validator->validate('invalid-email', $schema);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('value is not a valid email address', $errors[0]);
    }

    public function testValidatesFormatUrlCorrectly(): void
    {
        $schema = Schema::string()->format('url')->toArray();
        $this->assertEmpty($this->validator->validate('https://example.com', $schema));

        $errors = $this->validator->validate('invalid-url', $schema);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('value is not a valid URL', $errors[0]);
    }

    public function testValidatesFormatDateTimeCorrectly(): void
    {
        $schema = Schema::string()->format('date-time')->toArray();
        $this->assertEmpty($this->validator->validate('2026-06-06T12:00:00Z', $schema));
        $this->assertEmpty($this->validator->validate('2026-06-06T12:00:00+01:00', $schema));

        $errors = $this->validator->validate('2026-06-06', $schema);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('value is not a valid ISO 8601 date-time', $errors[0]);

        $errors2 = $this->validator->validate('invalid-datetime', $schema);
        $this->assertNotEmpty($errors2);
    }

    public function testValidatesFormatDateCorrectly(): void
    {
        $schema = Schema::string()->format('date')->toArray();
        $this->assertEmpty($this->validator->validate('2026-06-06', $schema));

        $errors = $this->validator->validate('06-06-2026', $schema);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('value is not a valid date (YYYY-MM-DD)', $errors[0]);
    }

    public function testSkipsUnknownFormats(): void
    {
        $schema = ['type' => 'string', 'format' => 'uuid'];
        $this->assertEmpty($this->validator->validate('some-uuid-string', $schema));
    }
}
