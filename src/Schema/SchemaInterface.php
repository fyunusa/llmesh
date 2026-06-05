<?php

declare(strict_types=1);

namespace LLMesh\Core\Schema;

/**
 * Contract for all JSON Schema nodes produced by the Schema builder.
 *
 * Implementations must be serialisable to a plain PHP array that is a
 * valid JSON Schema (draft-07 compatible) and to a JSON string.
 */
interface SchemaInterface
{
    /**
     * Return the JSON Schema as a plain PHP array.
     *
     * The returned array is suitable for json_encode() and can be passed
     * directly to an LLM API as a tool/function parameter schema.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Return the JSON Schema as a JSON string.
     *
     * @throws \JsonException If the schema cannot be encoded.
     */
    public function toJson(): string;
}
