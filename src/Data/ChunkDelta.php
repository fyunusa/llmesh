<?php

declare(strict_types=1);

namespace LLMesh\Core\Data;

/**
 * Data Transfer Object for stream chunk deltas.
 *
 * Represents a chunk of data received during streaming generation.
 */
final class ChunkDelta
{
    /**
     * Constructor.
     *
     * @param string|null $text Optional text content of this chunk
     * @param ToolCall|null $toolCall Optional tool call in this chunk
     * @param string|null $finishReason Optional reason the stream ended
     */
    public function __construct(
        public readonly ?string $text = null,
        public readonly ?ToolCall $toolCall = null,
        public readonly ?string $finishReason = null,
    ) {
    }

    /**
     * Create a text chunk.
     *
     * @param string $text The text content
     *
     * @return self
     */
    public static function text(string $text): self
    {
        return new self(text: $text);
    }

    /**
     * Create a tool call chunk.
     *
     * @param ToolCall $toolCall The tool call
     *
     * @return self
     */
    public static function toolCall(ToolCall $toolCall): self
    {
        return new self(toolCall: $toolCall);
    }

    /**
     * Create a finish reason chunk.
     *
     * @param string $finishReason The reason the stream finished
     *
     * @return self
     */
    public static function finish(string $finishReason): self
    {
        return new self(finishReason: $finishReason);
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'toolCall' => $this->toolCall?->toArray(),
            'finishReason' => $this->finishReason,
        ];
    }
}
