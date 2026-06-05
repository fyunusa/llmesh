<?php

declare(strict_types=1);

namespace LLMesh\Core\Schema;

/**
 * Fluent JSON Schema builder.
 *
 * Every factory method returns a new Schema instance. Every chainable modifier
 * returns the same instance (mutable builder pattern) to keep call sites clean.
 * Call `toArray()` or `toJson()` at the end to materialise the schema.
 *
 * Supported JSON Schema draft-07 keywords:
 *   type, properties, required, items, enum, description, default,
 *   nullable (via anyOf + null), minLength, maxLength, minimum, maximum, format.
 *
 * Usage:
 * ```php
 * $schema = Schema::object([
 *     'name'   => Schema::string()->required()->minLength(1),
 *     'age'    => Schema::integer()->minimum(0)->maximum(120),
 *     'email'  => Schema::string()->format('email'),
 *     'tags'   => Schema::array(Schema::string()),
 *     'status' => Schema::enum(['active', 'inactive']),
 *     'meta'   => Schema::object(['key' => Schema::string()]),
 * ])->required(['name', 'age']);
 *
 * echo $schema->toJson();
 * ```
 */
final class Schema implements SchemaInterface
{
    /** @var array<string, mixed> Accumulated JSON Schema keywords */
    private array $schema = [];

    /** @var bool Whether this node is individually marked required */
    private bool $isRequired = false;

    // -------------------------------------------------------------------------
    // Private constructor — use factory methods
    // -------------------------------------------------------------------------

    private function __construct(string $type)
    {
        $this->schema['type'] = $type;
    }

    // -------------------------------------------------------------------------
    // Factory methods
    // -------------------------------------------------------------------------

    /**
     * Create a `string` schema node.
     */
    public static function string(): self
    {
        return new self('string');
    }

    /**
     * Create an `integer` schema node.
     */
    public static function integer(): self
    {
        return new self('integer');
    }

    /**
     * Create a `number` (float/double) schema node.
     */
    public static function number(): self
    {
        return new self('number');
    }

    /**
     * Create a `boolean` schema node.
     */
    public static function boolean(): self
    {
        return new self('boolean');
    }

    /**
     * Create an `array` schema node whose items match `$itemSchema`.
     *
     * @param SchemaInterface $itemSchema Schema for each element in the array
     */
    public static function array(SchemaInterface $itemSchema): self
    {
        $node = new self('array');
        $node->schema['items'] = $itemSchema->toArray();

        return $node;
    }

    /**
     * Create an `object` schema node from a map of property schemas.
     *
     * Properties individually marked `.required()` are automatically added to
     * the object-level `required` list. You can also call `->required([...])` on
     * the object node to set or extend the list explicitly.
     *
     * @param array<string, SchemaInterface> $properties
     */
    public static function object(array $properties = []): self
    {
        $node = new self('object');
        $node->schema['properties'] = [];

        $autoRequired = [];

        foreach ($properties as $name => $propSchema) {
            /** @var Schema $propSchema */
            $node->schema['properties'][$name] = $propSchema->toArray();

            // Properties marked individually as required are hoisted to the
            // object-level required array.
            if ($propSchema instanceof self && $propSchema->isRequired) {
                $autoRequired[] = $name;
            }
        }

        if (!empty($autoRequired)) {
            $node->schema['required'] = $autoRequired;
        }

        return $node;
    }

    /**
     * Create an `enum` schema node.
     *
     * The type is inferred from the first value; if values are mixed the
     * `type` key is omitted and `enum` is used alone.
     *
     * @param list<scalar> $values
     */
    public static function enum(array $values): self
    {
        $node = new self('string'); // default type for enums

        // If the values are not all strings, remove the type constraint
        $allStrings = array_reduce(
            $values,
            fn (bool $carry, mixed $v) => $carry && is_string($v),
            true,
        );

        if (!$allStrings) {
            unset($node->schema['type']);
        }

        $node->schema['enum'] = $values;

        return $node;
    }

    // -------------------------------------------------------------------------
    // Chainable modifiers
    // -------------------------------------------------------------------------

    /**
     * Mark this property as required within its parent object.
     *
     * When used on a property inside `Schema::object([...])`, the property name
     * is automatically hoisted into the object's `required` array.
     *
     * When called on an object node with an array argument, it explicitly sets
     * the `required` list (overriding any auto-detected list).
     *
     * @param list<string>|null $fields For object nodes, list of required property names.
     *                                  Omit or pass null on property nodes.
     */
    public function required(?array $fields = null): self
    {
        if ($fields !== null) {
            // Called on an object node: set/replace the required list
            $existing = $this->schema['required'] ?? [];
            $this->schema['required'] = array_values(
                array_unique(array_merge($existing, $fields))
            );
        } else {
            // Called on a property node: flag it for parent hoisting
            $this->isRequired = true;
        }

        return $this;
    }

    /**
     * Allow `null` in addition to the declared type (sets `nullable: true`
     * and restructures as `anyOf: [{type}, {type: null}]`).
     */
    public function nullable(): self
    {
        $baseType = $this->schema['type'] ?? null;

        if ($baseType !== null) {
            // Restructure as anyOf to allow null alongside the base type
            unset($this->schema['type']);
            $this->schema['anyOf'] = [
                ['type' => $baseType],
                ['type' => 'null'],
            ];
        }

        return $this;
    }

    /**
     * Add a human-readable description to the schema.
     */
    public function description(string $description): self
    {
        $this->schema['description'] = $description;

        return $this;
    }

    /**
     * Set a default value for the property.
     */
    public function default(mixed $value): self
    {
        $this->schema['default'] = $value;

        return $this;
    }

    /**
     * Set the minimum string length (`minLength`).
     */
    public function minLength(int $min): self
    {
        $this->schema['minLength'] = $min;

        return $this;
    }

    /**
     * Set the maximum string length (`maxLength`).
     */
    public function maxLength(int $max): self
    {
        $this->schema['maxLength'] = $max;

        return $this;
    }

    /**
     * Set the minimum numeric value (`minimum`).
     */
    public function minimum(int|float $min): self
    {
        $this->schema['minimum'] = $min;

        return $this;
    }

    /**
     * Set the maximum numeric value (`maximum`).
     */
    public function maximum(int|float $max): self
    {
        $this->schema['maximum'] = $max;

        return $this;
    }

    /**
     * Set the string format (e.g. `'email'`, `'uri'`, `'date-time'`).
     */
    public function format(string $format): self
    {
        $this->schema['format'] = $format;

        return $this;
    }

    // -------------------------------------------------------------------------
    // SchemaInterface
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->schema;
    }

    /**
     * {@inheritdoc}
     */
    public function toJson(): string
    {
        return json_encode($this->schema, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
