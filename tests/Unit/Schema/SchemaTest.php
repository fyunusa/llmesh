<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Schema;

use LLMesh\Core\Schema\Schema;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\Schema\Schema
 */
final class SchemaTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Scalar factory methods
    // -------------------------------------------------------------------------

    public function testStringReturnsStringType(): void
    {
        $schema = Schema::string()->toArray();

        self::assertSame('string', $schema['type']);
        self::assertArrayNotHasKey('enum', $schema);
    }

    public function testIntegerReturnsIntegerType(): void
    {
        self::assertSame('integer', Schema::integer()->toArray()['type']);
    }

    public function testNumberReturnsNumberType(): void
    {
        self::assertSame('number', Schema::number()->toArray()['type']);
    }

    public function testBooleanReturnsBooleanType(): void
    {
        self::assertSame('boolean', Schema::boolean()->toArray()['type']);
    }

    // -------------------------------------------------------------------------
    // array()
    // -------------------------------------------------------------------------

    public function testArrayTypeWithItemSchema(): void
    {
        $schema = Schema::array(Schema::string())->toArray();

        self::assertSame('array', $schema['type']);
        self::assertIsArray($schema['items']);
        self::assertSame('string', $schema['items']['type']);
    }

    public function testArrayItemSchemaIsNested(): void
    {
        $schema = Schema::array(Schema::integer())->toArray();

        self::assertSame('integer', $schema['items']['type']);
    }

    // -------------------------------------------------------------------------
    // object()
    // -------------------------------------------------------------------------

    public function testObjectTypeWithProperties(): void
    {
        $schema = Schema::object([
            'name' => Schema::string(),
            'age'  => Schema::integer(),
        ])->toArray();

        self::assertSame('object', $schema['type']);
        self::assertArrayHasKey('properties', $schema);
        self::assertArrayHasKey('name', $schema['properties']);
        self::assertArrayHasKey('age', $schema['properties']);
        self::assertSame('string', $schema['properties']['name']['type']);
        self::assertSame('integer', $schema['properties']['age']['type']);
    }

    public function testObjectWithNoPropertiesHasEmptyPropertiesKey(): void
    {
        $schema = Schema::object()->toArray();

        self::assertSame('object', $schema['type']);
        self::assertSame([], $schema['properties']);
    }

    // -------------------------------------------------------------------------
    // enum()
    // -------------------------------------------------------------------------

    public function testEnumWithStringValues(): void
    {
        $schema = Schema::enum(['active', 'inactive'])->toArray();

        self::assertSame(['active', 'inactive'], $schema['enum']);
        self::assertSame('string', $schema['type']); // all strings → type string
    }

    public function testEnumWithMixedValuesDropsType(): void
    {
        $schema = Schema::enum(['a', 1, true])->toArray();

        self::assertSame(['a', 1, true], $schema['enum']);
        self::assertArrayNotHasKey('type', $schema);
    }

    // -------------------------------------------------------------------------
    // Chainable modifiers
    // -------------------------------------------------------------------------

    public function testRequiredOnPropertyNodeMarksItForHoisting(): void
    {
        // A required() call on a leaf node should be hoisted by the parent object
        $schema = Schema::object([
            'name' => Schema::string()->required(),
            'age'  => Schema::integer(),
        ])->toArray();

        self::assertContains('name', $schema['required']);
        self::assertNotContains('age', $schema['required']);
    }

    public function testRequiredOnObjectNodeSetsRequiredArray(): void
    {
        $schema = Schema::object([
            'name' => Schema::string(),
            'age'  => Schema::integer(),
        ])->required(['name', 'age'])->toArray();

        self::assertSame(['name', 'age'], $schema['required']);
    }

    public function testRequiredMergesAutoAndExplicit(): void
    {
        $schema = Schema::object([
            'name' => Schema::string()->required(),
            'email' => Schema::string(),
        ])->required(['email'])->toArray();

        self::assertContains('name', $schema['required']);
        self::assertContains('email', $schema['required']);
    }

    public function testNullableRestructuresToAnyOf(): void
    {
        $schema = Schema::string()->nullable()->toArray();

        self::assertArrayNotHasKey('type', $schema);
        self::assertArrayHasKey('anyOf', $schema);
        self::assertCount(2, $schema['anyOf']);

        $types = array_column($schema['anyOf'], 'type');
        self::assertContains('string', $types);
        self::assertContains('null', $types);
    }

    public function testDescriptionAddsKey(): void
    {
        $schema = Schema::string()->description('A short bio')->toArray();

        self::assertSame('A short bio', $schema['description']);
    }

    public function testDefaultAddsKey(): void
    {
        $schema = Schema::integer()->default(42)->toArray();

        self::assertSame(42, $schema['default']);
    }

    public function testMinLengthAddsKey(): void
    {
        $schema = Schema::string()->minLength(3)->toArray();

        self::assertSame(3, $schema['minLength']);
    }

    public function testMaxLengthAddsKey(): void
    {
        $schema = Schema::string()->maxLength(255)->toArray();

        self::assertSame(255, $schema['maxLength']);
    }

    public function testMinimumAddsKey(): void
    {
        $schema = Schema::integer()->minimum(0)->toArray();

        self::assertSame(0, $schema['minimum']);
    }

    public function testMaximumAddsKey(): void
    {
        $schema = Schema::number()->maximum(99.9)->toArray();

        self::assertSame(99.9, $schema['maximum']);
    }

    public function testFormatAddsKey(): void
    {
        $schema = Schema::string()->format('email')->toArray();

        self::assertSame('email', $schema['format']);
    }

    // -------------------------------------------------------------------------
    // toJson()
    // -------------------------------------------------------------------------

    public function testToJsonReturnsValidJsonString(): void
    {
        $json = Schema::object([
            'name' => Schema::string()->required(),
        ])->toJson();

        $decoded = json_decode($json, true);

        self::assertIsArray($decoded);
        self::assertSame('object', $decoded['type']);
    }

    public function testToJsonMatchesToArray(): void
    {
        $schema = Schema::object([
            'age' => Schema::integer()->minimum(0)->maximum(120),
        ]);

        $fromArray = $schema->toArray();
        $fromJson  = json_decode($schema->toJson(), associative: true);

        self::assertSame($fromArray, $fromJson);
    }

    // -------------------------------------------------------------------------
    // Full complex schema matching the spec example
    // -------------------------------------------------------------------------

    public function testFullComplexSchemaMatchesExpectedStructure(): void
    {
        $schema = Schema::object([
            'name'   => Schema::string()->required()->minLength(1),
            'age'    => Schema::integer()->minimum(0)->maximum(120),
            'email'  => Schema::string()->format('email'),
            'tags'   => Schema::array(Schema::string()),
            'status' => Schema::enum(['active', 'inactive']),
            'meta'   => Schema::object(['key' => Schema::string()]),
        ])->required(['name', 'age'])->toArray();

        self::assertSame('object', $schema['type']);
        self::assertContains('name', $schema['required']);
        self::assertContains('age', $schema['required']);
        self::assertSame(1, $schema['properties']['name']['minLength']);
        self::assertSame(0, $schema['properties']['age']['minimum']);
        self::assertSame(120, $schema['properties']['age']['maximum']);
        self::assertSame('email', $schema['properties']['email']['format']);
        self::assertSame('array', $schema['properties']['tags']['type']);
        self::assertSame(['active', 'inactive'], $schema['properties']['status']['enum']);
        self::assertSame('object', $schema['properties']['meta']['type']);
    }

    // -------------------------------------------------------------------------
    // Independent usability (no provider)
    // -------------------------------------------------------------------------

    public function testSchemaIsUsableWithoutProvider(): void
    {
        // This test asserts the schema builder stands alone
        $schema = Schema::string()->required()->minLength(2)->format('email');

        self::assertIsArray($schema->toArray());
        self::assertIsString($schema->toJson());
    }
}
