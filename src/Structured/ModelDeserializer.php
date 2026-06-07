<?php

declare(strict_types=1);

namespace LLMesh\Core\Structured;

use LLMesh\Core\Structured\Attributes\Field;
use LLMesh\Core\Exceptions\ValidationException;

final class ModelDeserializer
{
    private TypeCoercer $coercer;

    public function __construct()
    {
        $this->coercer = new TypeCoercer();
    }

    /**
     * Deserialize a raw array (decoded LLM JSON) into a typed LLMModel instance.
     *
     * @param array<string, mixed>   $data       Raw decoded JSON from LLM
     * @param class-string<LLMModel> $modelClass Target LLMModel subclass
     * @return LLMModel                          Fully typed instance
     * @throws ValidationException               If required fields are missing or types don't match
     */
    public function deserialize(array $data, string $modelClass): LLMModel
    {
        $reflection  = new \ReflectionClass($modelClass);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            throw new ValidationException(
                "$modelClass has no constructor",
                ["$modelClass has no constructor"]
            );
        }

        $args = [];

        foreach ($constructor->getParameters() as $parameter) {
            $paramName = $parameter->getName();
            $fieldName = $this->toSnakeCase($paramName);  // LLM returns snake_case

            $fieldAttr = $this->getFieldAttribute($parameter);

            // Try snake_case first, then camelCase, then original name
            $value = $data[$fieldName]
                ?? $data[$paramName]
                ?? $data[$this->toCamelCase($fieldName)]
                ?? null;

            // Handle missing value
            $keyExists = array_key_exists($fieldName, $data)
                || array_key_exists($paramName, $data)
                || array_key_exists($this->toCamelCase($fieldName), $data);

            if ($value === null && !$keyExists) {
                if ($parameter->isDefaultValueAvailable()) {
                    // Use PHP default value — don't add to args, PHP will use the default
                    continue;
                }

                // Check if Field attribute has a default
                if ($fieldAttr !== null && $fieldAttr->default !== null) {
                    $args[$paramName] = $fieldAttr->default;
                    continue;
                }

                // If the parameter allows null, it can default to null if it's optional,
                // but if it's required we throw validation error.
                throw new ValidationException(
                    "Required field '$fieldName' is missing from LLM response",
                    ["Required field '$fieldName' is missing from LLM response"]
                );
            }

            // Coerce the value to the expected PHP type
            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType) {
                // Handle array with items type (nested LLMModel array)
                if ($type->getName() === 'array' && $fieldAttr?->items !== null && is_array($value)) {
                    $args[$paramName] = $this->deserializeArray($value, $fieldAttr->items, $fieldName);
                    continue;
                }

                $args[$paramName] = $this->coercer->coerce($value, $type, $fieldName);
            } else {
                // Union types or untyped — pass raw value
                $args[$paramName] = $value;
            }
        }

        // Construct the model using named arguments
        $instance = $reflection->newInstanceArgs($this->resolveConstructorArgs($constructor, $args));

        // Run post-construction validation
        $instance->validate();

        return $instance;
    }

    /**
     * Deserialize an array of items where each item is an LLMModel or a primitive.
     */
    private function deserializeArray(array $items, string $itemsClass, string $fieldName): array
    {
        if (is_subclass_of($itemsClass, LLMModel::class)) {
            return array_map(
                function ($item) use ($itemsClass, $fieldName) {
                    if (!is_array($item)) {
                        throw new ValidationException(
                            "Each item in '$fieldName' must be an object, got " . gettype($item),
                            ["Each item in '$fieldName' must be an object, got " . gettype($item)]
                        );
                    }
                    return $this->deserialize($item, $itemsClass);
                },
                $items
            );
        }

        // Primitive items — return as-is
        return $items;
    }

    /**
     * Resolve constructor args in the correct positional order.
     * Named arguments in newInstanceArgs requires PHP 8.0+ / 8.1+ and an ordered array.
     */
    private function resolveConstructorArgs(\ReflectionMethod $constructor, array $namedArgs): array
    {
        $ordered = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $namedArgs)) {
                $ordered[] = $namedArgs[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $ordered[] = $param->getDefaultValue();
            }
            // If neither — the missing required field error was already thrown above
        }
        return $ordered;
    }

    private function getFieldAttribute(\ReflectionParameter $parameter): ?Field
    {
        $attrs = $parameter->getAttributes(Field::class);
        return isset($attrs[0]) ? $attrs[0]->newInstance() : null;
    }

    private function toSnakeCase(string $camelCase): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($camelCase)));
    }

    private function toCamelCase(string $snakeCase): string
    {
        return lcfirst(str_replace('_', '', ucwords($snakeCase, '_')));
    }
}
