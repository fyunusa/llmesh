<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

/**
 * Dispatched after the streaming generation has been fully consumed.
 *
 * This event is emitted by the wrapping generator inside `LLMesh::streamText()`
 * once the last chunk has been yielded, giving listeners access to aggregate
 * statistics: total chunks received and wall-clock duration.
 */
final class StreamCompleted
{
    /**
     * @param string $provider    Short provider name (e.g. "Anthropic", "OpenAI")
     * @param int    $totalChunks Total number of ChunkDelta objects yielded
     * @param int    $durationMs  Wall-clock duration in milliseconds from start to completion
     */
    public function __construct(
        public readonly string $provider,
        public readonly int $totalChunks,
        public readonly int $durationMs,
    ) {
    }
}
