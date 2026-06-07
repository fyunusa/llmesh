<?php

declare(strict_types=1);

namespace LLMesh\Core\Structured;

use LLMesh\Core\Structured\Attributes\Description;
use LLMesh\Core\Structured\Attributes\Field;
use LLMesh\Core\Exceptions\ValidationException;

final class SchemaGenerator
{
    /**
     * Generate a JSON Schema array from an LLMModel subclass.
     *
     * @param class-string<LLMModel> $modelClass
     * @return array<string, mixed> JSON Schema compatible array
     * @throws ValidationException if the class is not a valid LLMModel subclass
     */
    public function generate(string $modelClass): array
    {
        $this->assertIsLLMModel($modelClass);

        $reflection = new \ReflectionClass($modelClass);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            throw new ValidationException(
                "$modelClass must have a constructor to be used as an LLMModel",
                ["$modelClass must have a constructor to be used as an LLMModel"]
            );
        }

        $schema = [
            'type'       => 'object',
            'properties' => [],
            'required'   => [],
        ];

        // Add class-level description if present
        $descriptionAttr = $reflection->getAttributes(Description::class)[0] ?? null;
        if ($descriptionAttr !== null) {
            $schema['description'] = $descriptionAttr->newInstance()->text;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $fieldName   = $this->toSnakeCase($parameter->getName());
            $fieldSchema = $this->buildFieldSchema($parameter);

            $schema['properties'][$fieldName] = $fieldSchema;

            // A field is required if it has no default value and does not allow default in attribute
            $fieldAttr = $this->getFieldAttribute($parameter);

            $isOptional = $parameter->isDefaultValueAvailable() || $parameter->isOptional();
            if ($fieldAttr !== null && !$fieldAttr->required) {
                $isOptional = true;
            }

            if (!$isOptional) {
                $schema['required'][] = $fieldName;
            }
        }

        // Remove duplicates in required array
        $schema['required'] = array_unique($schema['required']);

        if (empty($schema['required'])) {
            unset($schema['required']);
        }

        return $schema;
    }

    private function buildFieldSchema(\ReflectionParameter $parameter): array
    {
        $type      = $parameter->getType();
        $fieldAttr = $this->getFieldAttribute($parameter);
        $schema    = [];

        // Map the PHP type to JSON Schema type
        if ($type instanceof \ReflectionNamedType) {
            $schema = $this->mapTypeToSchema($type, $fieldAttr);
        } elseif ($type instanceof \ReflectionUnionType) {
            // Union types: generate anyOf schema
            $schema['anyOf'] = array_map(
                fn(\ReflectionNamedType $t) => $this->mapTypeToSchema($t, $fieldAttr),
                $type->getTypes()
            );
        }

        // Apply Field attribute constraints
        if ($fieldAttr !== null) {
            if ($fieldAttr->description !== '') {
                $schema['description'] = $fieldAttr->description;
            }
            if ($fieldAttr->example !== null) {
                $schema['examples'] = [$fieldAttr->example];
            }
            if ($fieldAttr->minLength !== null) {
                $schema['minLength'] = $fieldAttr->minLength;
            }
            if ($fieldAttr->maxLength !== null) {
                $schema['maxLength'] = $fieldAttr->maxLength;
            }
            if ($fieldAttr->minimum !== null) {
                $schema['minimum'] = $fieldAttr->minimum;
            }
            if ($fieldAttr->maximum !== null) {
                $schema['maximum'] = $fieldAttr->maximum;
            }
            if ($fieldAttr->pattern !== null) {
                $schema['pattern'] = $fieldAttr->pattern;
            }
            if ($fieldAttr->default !== null) {
                $schema['default'] = $fieldAttr->default;
            }
        }

        // Handle nullable: if parameter type is nullable, add null to type
        if ($type instanceof \ReflectionNamedType && $type->allowsNull()) {
            $schema = ['oneOf' => [$schema, ['type' => 'null']]];
        }

        return $schema;
    }

    private function mapTypeToSchema(\ReflectionNamedType $type, ?Field $fieldAttr): array
    {
        $typeName = $type->getName();

        return match (true) {
            $typeName === 'string'                      => ['type' => 'string'],
            $typeName === 'int'                         => ['type' => 'integer'],
            $typeName === 'float'                       => ['type' => 'number'],
            $typeName === 'bool'                        => ['type' => 'boolean'],
            $typeName === 'array'                       => $this->buildArraySchema($fieldAttr),
            $typeName === \DateTimeImmutable::class     => [
                'type'        => 'string',
                'format'      => 'date-time',
                'description' => 'ISO 8601 datetime string',
            ],
            $typeName === \DateTime::class              => [
                'type'   => 'string',
                'format' => 'date-time',
            ],
            $this->isLLMModel($typeName)               => $this->generate($typeName),
            $this->isBackedEnum($typeName)              => $this->buildEnumSchema($typeName),
            default                                     => ['type' => 'string'],
        };
    }

    private function buildArraySchema(?Field $fieldAttr): array
    {
        $schema = ['type' => 'array'];

        if ($fieldAttr !== null && $fieldAttr->items !== null) {
            $itemsClass = $fieldAttr->items;

            if ($this->isLLMModel($itemsClass)) {
                // Nested LLMModel — recursively generate schema
                $schema['items'] = $this->generate($itemsClass);
            } else {
                // Primitive type hint for items
                $schema['items'] = ['type' => $this->phpTypeToJsonType($itemsClass)];
            }
        } else {
            // No items hint — allow any type in array
            $schema['items'] = [];
        }

        return $schema;
    }

    private function buildEnumSchema(string $enumClass): array
    {
        $reflection = new \ReflectionEnum($enumClass);
        $cases      = $reflection->getCases();
        $backingType = $reflection->getBackingType();
        $type = 'string';
        if ($backingType instanceof \ReflectionNamedType && $backingType->getName() === 'int') {
            $type = 'integer';
        }

        return [
            'type' => $type,
            'enum' => array_map(
                fn(\ReflectionEnumBackedCase $case) => $case->getBackingValue(),
                $cases
            ),
        ];
    }

    private function getFieldAttribute(\ReflectionParameter $parameter): ?Field
    {
        $attrs = $parameter->getAttributes(Field::class);
        return isset($attrs[0]) ? $attrs[0]->newInstance() : null;
    }

    private function assertIsLLMModel(string $class): void
    {
        if (!class_exists($class) || !is_subclass_of($class, LLMModel::class)) {
            throw new ValidationException(
                "$class must extend LLMesh\\Core\\Structured\\LLMModel",
                ["$class must extend LLMesh\\Core\\Structured\\LLMModel"]
            );
        }
    }

    private function isLLMModel(string $class): bool
    {
        return class_exists($class) && is_subclass_of($class, LLMModel::class);
    }

    private function isBackedEnum(string $class): bool
    {
        return enum_exists($class) && (new \ReflectionEnum($class))->isBacked();
    }

    private function phpTypeToJsonType(string $phpType): string
    {
        return match ($phpType) {
            'int'   => 'integer',
            'float' => 'number',
            'bool'  => 'boolean',
            default => 'string',
        };
    }

    private function toSnakeCase(string $camelCase): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($camelCase)));
    }
}
