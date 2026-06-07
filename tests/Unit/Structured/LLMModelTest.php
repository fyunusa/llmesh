<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Structured;

use LLMesh\Core\Structured\LLMModel;
use LLMesh\Core\Structured\Attributes\Field;
use PHPUnit\Framework\TestCase;

enum TestStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

class TestNestedModel extends LLMModel
{
    public function __construct(
        public readonly string $nestedName,
    ) {}
}

class TestMainModel extends LLMModel
{
    public function __construct(
        public readonly string $title,
        public readonly \DateTimeImmutable $createdAt,
        public readonly TestStatus $status,
        #[Field(items: TestNestedModel::class)]
        public readonly array $nestedItems,
        public readonly ?TestNestedModel $singleNested = null,
    ) {}
}

final class LLMModelTest extends TestCase
{
    public function testToArraySerializesRecursively(): void
    {
        $createdAt = new \DateTimeImmutable('2026-06-07T09:00:00Z');
        $nested1 = new TestNestedModel('nest 1');
        $nested2 = new TestNestedModel('nest 2');
        $single = new TestNestedModel('single nest');

        $model = new TestMainModel(
            title: 'Hello Title',
            createdAt: $createdAt,
            status: TestStatus::Active,
            nestedItems: [$nested1, $nested2],
            singleNested: $single
        );

        $expected = [
            'title' => 'Hello Title',
            'created_at' => $createdAt->format(\DateTimeInterface::ATOM),
            'status' => 'active',
            'nested_items' => [
                ['nested_name' => 'nest 1'],
                ['nested_name' => 'nest 2'],
            ],
            'single_nested' => [
                'nested_name' => 'single nest',
            ],
        ];

        $this->assertSame($expected, $model->toArray());
    }

    public function testToJsonReturnsValidJson(): void
    {
        $createdAt = new \DateTimeImmutable('2026-06-07T09:00:00Z');
        $model = new TestMainModel(
            title: 'Title',
            createdAt: $createdAt,
            status: TestStatus::Inactive,
            nestedItems: []
        );

        $json = $model->toJson();
        $decoded = json_decode($json, true);

        $this->assertSame('Title', $decoded['title']);
        $this->assertSame('inactive', $decoded['status']);
    }

    public function testEqualsComparesArrayContent(): void
    {
        $createdAt = new \DateTimeImmutable('2026-06-07T09:00:00Z');
        $model1 = new TestMainModel(
            title: 'Title',
            createdAt: $createdAt,
            status: TestStatus::Inactive,
            nestedItems: []
        );

        $model2 = new TestMainModel(
            title: 'Title',
            createdAt: $createdAt,
            status: TestStatus::Inactive,
            nestedItems: []
        );

        $model3 = new TestMainModel(
            title: 'Different',
            createdAt: $createdAt,
            status: TestStatus::Inactive,
            nestedItems: []
        );

        $this->assertTrue($model1->equals($model2));
        $this->assertFalse($model1->equals($model3));
    }

    public function testValidateIsNoOpByDefault(): void
    {
        $model = new TestNestedModel('test');
        // This should not throw any exception
        $model->validate();
        $this->assertTrue(true);
    }
}
