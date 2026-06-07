<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Structured;

use LLMesh\Core\Structured\LLMModel;
use LLMesh\Core\Structured\ModelDeserializer;
use LLMesh\Core\Structured\Attributes\Field;
use LLMesh\Core\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class DeserNested extends LLMModel
{
    public function __construct(
        public readonly string $nestedName,
    ) {}
}

class DeserModel extends LLMModel
{
    public function __construct(
        public readonly string $fullName,
        public readonly int $age,
        #[Field(default: 'USA')]
        public readonly string $country = 'USA',
        public readonly string $occupation = 'Developer',
        #[Field(items: DeserNested::class)]
        public readonly array $hobbies = [],
        public readonly ?DeserNested $favoriteHobby = null,
    ) {}

    public function validate(): void
    {
        if ($this->age < 0) {
            throw new \InvalidArgumentException('Age cannot be negative');
        }
    }
}

final class ModelDeserializerTest extends TestCase
{
    private ModelDeserializer $deserializer;

    protected function setUp(): void
    {
        $this->deserializer = new ModelDeserializer();
    }

    public function testSuccessfulDeserialization(): void
    {
        $data = [
            'full_name' => 'John Doe',
            'age' => 30,
            'occupation' => 'Architect',
            'hobbies' => [
                ['nested_name' => 'Reading'],
                ['nested_name' => 'Coding'],
            ],
            'favorite_hobby' => [
                'nested_name' => 'Gaming',
            ],
        ];

        /** @var DeserModel $model */
        $model = $this->deserializer->deserialize($data, DeserModel::class);

        $this->assertInstanceOf(DeserModel::class, $model);
        $this->assertSame('John Doe', $model->fullName);
        $this->assertSame(30, $model->age);
        $this->assertSame('USA', $model->country); // from Field default or PHP default
        $this->assertSame('Architect', $model->occupation); // overwritten
        $this->assertCount(2, $model->hobbies);
        $this->assertSame('Reading', $model->hobbies[0]->nestedName);
        $this->assertSame('Coding', $model->hobbies[1]->nestedName);
        $this->assertSame('Gaming', $model->favoriteHobby->nestedName);
    }

    public function testCamelCaseFallbackAccepted(): void
    {
        $data = [
            'fullName' => 'Jane Doe', // camelCase instead of full_name
            'age' => '25', // numeric string coerced
        ];

        /** @var DeserModel $model */
        $model = $this->deserializer->deserialize($data, DeserModel::class);

        $this->assertSame('Jane Doe', $model->fullName);
        $this->assertSame(25, $model->age);
    }

    public function testMissingRequiredFieldThrowsValidationException(): void
    {
        $data = [
            'age' => 30,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Required field 'full_name' is missing");
        $this->deserializer->deserialize($data, DeserModel::class);
    }

    public function testPhpDefaultUsedWhenAbsent(): void
    {
        $data = [
            'full_name' => 'Jane Doe',
            'age' => 25,
        ];

        /** @var DeserModel $model */
        $model = $this->deserializer->deserialize($data, DeserModel::class);
        $this->assertSame('Developer', $model->occupation); // PHP default
    }

    public function testValidationFailsAfterConstruction(): void
    {
        $data = [
            'full_name' => 'Bad Age',
            'age' => -5,
        ];

        // Should call validate() and propagate the exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Age cannot be negative');
        $this->deserializer->deserialize($data, DeserModel::class);
    }

    public function testInvalidTypeThrowsValidationException(): void
    {
        $data = [
            'full_name' => 'Jane Doe',
            'age' => 'not-an-int', // invalid int
        ];

        $this->expectException(ValidationException::class);
        $this->deserializer->deserialize($data, DeserModel::class);
    }
}
