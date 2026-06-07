<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Structured;

use LLMesh\Core\Structured\LLMModel;
use LLMesh\Core\Structured\SchemaGenerator;
use LLMesh\Core\Structured\Attributes\Description;
use LLMesh\Core\Structured\Attributes\Field;
use LLMesh\Core\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

enum EnumIntStatus: int
{
    case Draft = 1;
    case Published = 2;
}

enum EnumStringStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}

#[Description('My description test')]
class SchemaTestNestedModel extends LLMModel
{
    public function __construct(
        #[Field(description: 'Nested title')]
        public readonly string $nestedTitle,
    ) {}
}

class SchemaTestMainModel extends LLMModel
{
    public function __construct(
        #[Field(description: 'The title of post', minLength: 5, maxLength: 50, example: 'Sample Post')]
        public readonly string $title,

        #[Field(minimum: 1, maximum: 10)]
        public readonly int $rating,

        public readonly float $weight,
        public readonly bool $isPublished,
        public readonly \DateTimeImmutable $publishedAt,
        public readonly EnumStringStatus $stringStatus,
        public readonly EnumIntStatus $intStatus,

        #[Field(items: SchemaTestNestedModel::class)]
        public readonly array $items,

        public readonly SchemaTestNestedModel $nestedObj,
        public readonly ?string $optionalString,

        public readonly string $withDefault = 'default value',
    ) {}
}

class NoConstructorModel extends LLMModel
{
}

final class SchemaGeneratorTest extends TestCase
{
    public function testGenerateValidSchema(): void
    {
        $generator = new SchemaGenerator();
        $schema = $generator->generate(SchemaTestMainModel::class);

        // Core asserts
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);

        $properties = $schema['properties'];

        // string property
        $this->assertSame('string', $properties['title']['type']);
        $this->assertSame('The title of post', $properties['title']['description']);
        $this->assertSame(5, $properties['title']['minLength']);
        $this->assertSame(50, $properties['title']['maxLength']);
        $this->assertSame(['Sample Post'], $properties['title']['examples']);

        // int property
        $this->assertSame('integer', $properties['rating']['type']);
        $this->assertSame(1, $properties['rating']['minimum']);
        $this->assertSame(10, $properties['rating']['maximum']);

        // float property
        $this->assertSame('number', $properties['weight']['type']);

        // bool property
        $this->assertSame('boolean', $properties['is_published']['type']);

        // DateTime property
        $this->assertSame('string', $properties['published_at']['type']);
        $this->assertSame('date-time', $properties['published_at']['format']);

        // string enum property
        $this->assertSame('string', $properties['string_status']['type']);
        $this->assertSame(['open', 'closed'], $properties['string_status']['enum']);

        // int enum property
        $this->assertSame('integer', $properties['int_status']['type']);
        $this->assertSame([1, 2], $properties['int_status']['enum']);

        // array items property
        $this->assertSame('array', $properties['items']['type']);
        $this->assertSame('object', $properties['items']['items']['type']);
        $this->assertSame('Nested title', $properties['items']['items']['properties']['nested_title']['description']);
        $this->assertSame('My description test', $properties['items']['items']['description']);

        // nested object property
        $this->assertSame('object', $properties['nested_obj']['type']);
        $this->assertSame('My description test', $properties['nested_obj']['description']);

        // nullable property
        $this->assertArrayHasKey('oneOf', $properties['optional_string']);
        $this->assertSame('string', $properties['optional_string']['oneOf'][0]['type']);
        $this->assertSame('null', $properties['optional_string']['oneOf'][1]['type']);

        // check required fields
        $required = $schema['required'];
        $this->assertContains('title', $required);
        $this->assertContains('rating', $required);
        $this->assertContains('weight', $required);
        $this->assertContains('is_published', $required);
        $this->assertContains('published_at', $required);
        $this->assertContains('string_status', $required);
        $this->assertContains('int_status', $required);
        $this->assertContains('items', $required);
        $this->assertContains('nested_obj', $required);
        $this->assertContains('optional_string', $required);

        // optional parameter with PHP default should not be required
        $this->assertNotContains('with_default', $required);
    }

    public function testNonLLMModelThrowsValidationException(): void
    {
        $generator = new SchemaGenerator();
        $this->expectException(ValidationException::class);
        $generator->generate(\stdClass::class);
    }

    public function testNoConstructorThrowsValidationException(): void
    {
        $generator = new SchemaGenerator();
        $this->expectException(ValidationException::class);
        $generator->generate(NoConstructorModel::class);
    }
}
