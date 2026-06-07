<?php

declare(strict_types=1);

namespace LLMesh\Core\Structured\Attributes;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Field
{
    /**
     * @param string $description Human-readable description sent to LLM in JSON Schema
     * @param mixed $example Example value shown to LLM
     * @param int|null $minLength For string properties: minimum string length
     * @param int|null $maxLength For string properties: maximum string length
     * @param int|float|null $minimum For numeric properties: minimum value (inclusive)
     * @param int|float|null $maximum For numeric properties: maximum value (inclusive)
     * @param class-string<\LLMesh\Core\Structured\LLMModel>|null $items For array properties: the LLMModel subclass of each item
     * @param bool $required Whether this field is required in the schema
     * @param mixed $default Default value used if LLM omits the field
     * @param string|null $pattern Regex pattern constraint for string fields
     */
    public function __construct(
        public readonly string $description = '',
        public readonly mixed $example = null,
        public readonly ?int $minLength = null,
        public readonly ?int $maxLength = null,
        public readonly int|float|null $minimum = null,
        public readonly int|float|null $maximum = null,
        public readonly ?string $items = null,
        public readonly bool $required = true,
        public readonly mixed $default = null,
        public readonly ?string $pattern = null,
    ) {
        if ($this->items !== null) {
            if (!class_exists($this->items) || !is_subclass_of($this->items, \LLMesh\Core\Structured\LLMModel::class)) {
                throw new \InvalidArgumentException("Field \$items must be a class-string of an LLMModel subclass, got '{$this->items}'");
            }
        }
    }
}
