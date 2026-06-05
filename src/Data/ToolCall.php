<?php

declare(strict_types=1);

namespace LLMesh\Core\Data;

/**
 * Data Transfer Object for tool calls from LLM responses.
 */
final readonly class ToolCall
{
    /**
     * Constructor.
     *
     * @param string $id Unique identifier for this tool call
     * @param string $name Name of the tool to call
     * @param array $arguments Arguments to pass to the tool
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
    ) {
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }
}
