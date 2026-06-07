<?php

declare(strict_types=1);

namespace LLMesh\Core\Structured;

use LLMesh\Core\Exceptions\ValidationException;

final class TypeCoercer
{
    /**
     * Coerce a raw value from the LLM JSON response into the PHP type
     * declared on a constructor parameter.
     *
     * @param mixed                $value     Raw value from LLM JSON (string, int, float, bool, array, null)
     * @param \ReflectionNamedType $type      The expected PHP type from ReflectionParameter
     * @param string               $fieldName Used in error messages
     * @return mixed                          The coerced value, matching the expected PHP type
     * @throws ValidationException            If the value cannot be coerced to the expected type
     */
    public function coerce(
        mixed $value,
        \ReflectionNamedType $type,
        string $fieldName,
    ): mixed {
        // Allow null if type allows it and value is null
        if ($value === null) {
            if ($type->allowsNull()) {
                return null;
            }
            throw new ValidationException(
                "Field '$fieldName' cannot be null",
                ["Field '$fieldName' cannot be null"]
            );
        }

        $typeName = $type->getName();

        return match (true) {
            $typeName === 'string'                  => $this->toString($value, $fieldName),
            $typeName === 'int'                     => $this->toInt($value, $fieldName),
            $typeName === 'float'                   => $this->toFloat($value, $fieldName),
            $typeName === 'bool'                    => $this->toBool($value, $fieldName),
            $typeName === 'array'                   => $this->toArray($value, $fieldName),
            $typeName === \DateTimeImmutable::class => $this->toDateTimeImmutable($value, $fieldName),
            $typeName === \DateTime::class          => $this->toDateTime($value, $fieldName),
            $this->isLLMModel($typeName)            => $this->toNestedModel($value, $typeName, $fieldName),
            $this->isBackedEnum($typeName)          => $this->toEnum($value, $typeName, $fieldName),
            default                                 => $value,
        };
    }

    private function toString(mixed $value, string $field): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        throw new ValidationException(
            "Field '$field' expected string, got " . gettype($value),
            ["Field '$field' expected string, got " . gettype($value)]
        );
    }

    private function toInt(mixed $value, string $field): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        throw new ValidationException(
            "Field '$field' expected integer, got " . gettype($value),
            ["Field '$field' expected integer, got " . gettype($value)]
        );
    }

    private function toFloat(mixed $value, string $field): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        throw new ValidationException(
            "Field '$field' expected float, got " . gettype($value),
            ["Field '$field' expected float, got " . gettype($value)]
        );
    }

    private function toBool(mixed $value, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        // LLMs sometimes return "true"/"false" strings
        if ($value === 'true' || $value === '1' || $value === 1) {
            return true;
        }
        if ($value === 'false' || $value === '0' || $value === 0) {
            return false;
        }
        throw new ValidationException(
            "Field '$field' expected boolean, got " . gettype($value),
            ["Field '$field' expected boolean, got " . gettype($value)]
        );
    }

    private function toArray(mixed $value, string $field): array
    {
        if (is_array($value)) {
            return $value;
        }
        throw new ValidationException(
            "Field '$field' expected array, got " . gettype($value),
            ["Field '$field' expected array, got " . gettype($value)]
        );
    }

    private function toDateTimeImmutable(mixed $value, string $field): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if (!is_string($value)) {
            throw new ValidationException(
                "Field '$field' expected date string, got " . gettype($value),
                ["Field '$field' expected date string, got " . gettype($value)]
            );
        }

        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d', $value)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);

        if ($date !== false) {
            return $date;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable $e) {
            throw new ValidationException(
                "Field '$field' contains an unparseable date string: '$value'",
                ["Field '$field' contains an unparseable date string: '$value'"]
            );
        }
    }

    private function toDateTime(mixed $value, string $field): \DateTime
    {
        $immutable = $this->toDateTimeImmutable($value, $field);
        return \DateTime::createFromImmutable($immutable);
    }

    private function toNestedModel(mixed $value, string $modelClass, string $field): LLMModel
    {
        if (!is_array($value)) {
            throw new ValidationException(
                "Field '$field' expected object array for $modelClass, got " . gettype($value),
                ["Field '$field' expected object array for $modelClass, got " . gettype($value)]
            );
        }
        // Recursively deserialize nested LLMModel
        $deserializer = new ModelDeserializer();
        return $deserializer->deserialize($value, $modelClass);
    }

    private function toEnum(mixed $value, string $enumClass, string $field): \BackedEnum
    {
        if (!is_string($value) && !is_int($value)) {
            $validValues = implode(', ', array_map(
                fn($case) => "'{$case->value}'",
                $enumClass::cases()
            ));
            throw new ValidationException(
                "Field '$field' has invalid enum value. Expected string or integer, got " . gettype($value) . ". Valid values: $validValues",
                ["Field '$field' has invalid enum value. Expected string or integer, got " . gettype($value) . ". Valid values: $validValues"]
            );
        }
        $enum = $enumClass::tryFrom($value);
        if ($enum === null) {
            $validValues = implode(', ', array_map(
                fn($case) => "'{$case->value}'",
                $enumClass::cases()
            ));
            throw new ValidationException(
                "Field '$field' has invalid enum value '$value'. Valid values: $validValues",
                ["Field '$field' has invalid enum value '$value'. Valid values: $validValues"]
            );
        }
        return $enum;
    }

    private function isLLMModel(string $class): bool
    {
        return class_exists($class) && is_subclass_of($class, LLMModel::class);
    }

    private function isBackedEnum(string $class): bool
    {
        return enum_exists($class) && (new \ReflectionEnum($class))->isBacked();
    }
}
