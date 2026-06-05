<?php

declare(strict_types=1);

namespace LLMesh\Core\Tools;

use LLMesh\Core\Exceptions\ValidationException;

/**
 * A single parameter definition for a Tool.
 *
 * Chainable builder that produces a JSON Schema fragment.
 * Used inside `Tool::parameters([...])`:
 *
 * ```php
 * Tool::make('search')
 *     ->parameters([
 *         'query' => ToolParameter::string('Search query')->required(),
 *         'limit' => ToolParameter::integer('Max results')->minimum(1)->maximum(100)->default(10),
 *     ]);
 * ```
 */
final class ToolParameter
{
    /** @var array<string, mixed> Accumulated JSON Schema keywords */
    private array $schema = [];

    /** @var bool Whether this parameter is required */
    private bool $isRequired = false;

    // -------------------------------------------------------------------------
    // Private constructor
    // -------------------------------------------------------------------------

    private function __construct(string $type)
    {
        $this->schema['type'] = $type;
    }

    // -------------------------------------------------------------------------
    // Factory methods
    // -------------------------------------------------------------------------

    public static function string(string $description = ''): self
    {
        $p = new self('string');
        if ($description !== '') {
            $p->schema['description'] = $description;
        }
        return $p;
    }

    public static function integer(string $description = ''): self
    {
        $p = new self('integer');
        if ($description !== '') {
            $p->schema['description'] = $description;
        }
        return $p;
    }

    public static function number(string $description = ''): self
    {
        $p = new self('number');
        if ($description !== '') {
            $p->schema['description'] = $description;
        }
        return $p;
    }

    public static function boolean(string $description = ''): self
    {
        $p = new self('boolean');
        if ($description !== '') {
            $p->schema['description'] = $description;
        }
        return $p;
    }

    /**
     * Create an enum parameter.
     *
     * @param list<scalar> $values  Allowed values
     * @param string       $description Optional description
     */
    public static function enum(array $values, string $description = ''): self
    {
        // Determine type from values — string if all strings, omit otherwise
        $allStrings = array_reduce(
            $values,
            fn (bool $carry, mixed $v) => $carry && is_string($v),
            true,
        );

        $p = new self($allStrings ? 'string' : 'mixed');
        if (!$allStrings) {
            unset($p->schema['type']);
        }
        $p->schema['enum'] = $values;
        if ($description !== '') {
            $p->schema['description'] = $description;
        }
        return $p;
    }

    // -------------------------------------------------------------------------
    // Chainable modifiers
    // -------------------------------------------------------------------------

    public function required(): self
    {
        $this->isRequired = true;
        return $this;
    }

    public function description(string $description): self
    {
        $this->schema['description'] = $description;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->schema['default'] = $value;
        return $this;
    }

    public function minimum(int|float $min): self
    {
        $this->schema['minimum'] = $min;
        return $this;
    }

    public function maximum(int|float $max): self
    {
        $this->schema['maximum'] = $max;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Whether this parameter is marked as required.
     */
    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    /**
     * Return the JSON Schema array for this parameter.
     *
     * @return array<string, mixed>
     */
    public function toSchemaArray(): array
    {
        return $this->schema;
    }
}
