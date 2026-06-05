<?php

declare(strict_types=1);

namespace LLMesh\Core\Schema;

/**
 * Lightweight JSON Schema (draft-07 subset) validator.
 *
 * Validates a decoded PHP value against a schema array produced by the
 * {@see Schema} builder. Covers the keywords used by the builder:
 *   type, properties, required, items, enum, anyOf,
 *   minLength, maxLength, minimum, maximum, format.
 *
 * This is intentionally a self-contained implementation — it adds no
 * third-party dependencies. Complex schemas (allOf, $ref, etc.) are not
 * supported; they are outside the scope of LLMesh's structured-output use.
 */
final class SchemaValidator
{
    /**
     * Validate `$data` against `$schema`.
     *
     * @param mixed              $data   Decoded PHP value to validate
     * @param array<string,mixed> $schema JSON Schema array from Schema::toArray()
     *
     * @return list<string> List of human-readable error messages; empty = valid
     */
    public function validate(mixed $data, array $schema): array
    {
        return $this->validateNode($data, $schema, '$');
    }

    // -------------------------------------------------------------------------
    // Internal recursive validator
    // -------------------------------------------------------------------------

    /**
     * @param  array<string,mixed> $schema
     * @return list<string>
     */
    private function validateNode(mixed $data, array $schema, string $path): array
    {
        $errors = [];

        // --- anyOf (nullable support) ---
        if (isset($schema['anyOf'])) {
            foreach ($schema['anyOf'] as $subSchema) {
                if (empty($this->validateNode($data, $subSchema, $path))) {
                    return []; // passes at least one branch
                }
            }
            $errors[] = "{$path}: value does not match any of the allowed schemas";

            return $errors;
        }

        // --- type ---
        if (isset($schema['type'])) {
            $typeErrors = $this->validateType($data, $schema['type'], $path);
            if (!empty($typeErrors)) {
                return $typeErrors; // no point checking further constraints
            }
        }

        // --- enum ---
        if (isset($schema['enum'])) {
            if (!in_array($data, $schema['enum'], true)) {
                $allowed = implode(', ', array_map(
                    fn ($v) => json_encode($v),
                    $schema['enum'],
                ));
                $errors[] = "{$path}: value must be one of [{$allowed}]";
            }
        }

        // --- string constraints ---
        if (is_string($data)) {
            if (isset($schema['minLength']) && mb_strlen($data) < $schema['minLength']) {
                $errors[] = "{$path}: string length must be >= {$schema['minLength']}";
            }
            if (isset($schema['maxLength']) && mb_strlen($data) > $schema['maxLength']) {
                $errors[] = "{$path}: string length must be <= {$schema['maxLength']}";
            }
            if (isset($schema['format'])) {
                $formatError = $this->validateFormat($data, $schema['format'], $path);
                if ($formatError !== null) {
                    $errors[] = $formatError;
                }
            }
        }

        // --- numeric constraints ---
        if (is_int($data) || is_float($data)) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                $errors[] = "{$path}: value must be >= {$schema['minimum']}";
            }
            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                $errors[] = "{$path}: value must be <= {$schema['maximum']}";
            }
        }

        // --- object ---
        if (isset($schema['type']) && $schema['type'] === 'object' && is_array($data)) {
            // required fields
            foreach ($schema['required'] ?? [] as $requiredKey) {
                if (!array_key_exists($requiredKey, $data)) {
                    $errors[] = "{$path}: missing required property \"{$requiredKey}\"";
                }
            }

            // property schemas
            foreach ($schema['properties'] ?? [] as $propName => $propSchema) {
                if (array_key_exists($propName, $data)) {
                    $childErrors = $this->validateNode(
                        $data[$propName],
                        $propSchema,
                        "{$path}.{$propName}",
                    );
                    $errors = array_merge($errors, $childErrors);
                }
            }
        }

        // --- array ---
        if (isset($schema['type']) && $schema['type'] === 'array' && is_array($data)) {
            foreach ($data as $idx => $item) {
                if (isset($schema['items'])) {
                    $itemErrors = $this->validateNode(
                        $item,
                        $schema['items'],
                        "{$path}[{$idx}]",
                    );
                    $errors = array_merge($errors, $itemErrors);
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateType(mixed $data, string $type, string $path): array
    {
        $ok = match ($type) {
            'string'  => is_string($data),
            'integer' => is_int($data),
            'number'  => is_int($data) || is_float($data),
            'boolean' => is_bool($data),
            'array'   => is_array($data) && array_is_list($data),
            'object'  => is_array($data) && !array_is_list($data),
            'null'    => $data === null,
            default   => true, // unknown type — skip
        };

        if (!$ok) {
            return ["{$path}: expected type \"{$type}\", got " . gettype($data)];
        }

        return [];
    }

    private function validateFormat(string $data, string $format, string $path): ?string
    {
        return match ($format) {
            'email'     => filter_var($data, FILTER_VALIDATE_EMAIL) === false
                            ? "{$path}: value is not a valid email address"
                            : null,
            'uri', 'url' => filter_var($data, FILTER_VALIDATE_URL) === false
                            ? "{$path}: value is not a valid URL"
                            : null,
            'date-time' => $this->isValidDateTime($data)
                            ? null
                            : "{$path}: value is not a valid ISO 8601 date-time",
            'date'      => $this->isValidDate($data)
                            ? null
                            : "{$path}: value is not a valid date (YYYY-MM-DD)",
            default     => null, // unrecognised format — skip
        };
    }

    private function isValidDateTime(string $value): bool
    {
        try {
            new \DateTimeImmutable($value);
            // Must match ISO 8601 pattern
            return (bool) preg_match(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
                $value,
            );
        } catch (\Exception) {
            return false;
        }
    }

    private function isValidDate(string $value): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $dt !== false && $dt->format('Y-m-d') === $value;
    }
}
