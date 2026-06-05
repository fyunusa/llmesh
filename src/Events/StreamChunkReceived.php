<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

use LLMesh\Core\Data\ChunkDelta;

/**
 * Dispatched each time a new chunk is received during streaming.
 *
 * Carries the ChunkDelta and its sequential index (0-based) so listeners
 * can implement progress tracking, logging, or real-time forwarding.
 */
final readonly class StreamChunkReceived
{
    /**
     * @param ChunkDelta $chunk      The received chunk delta
     * @param int        $chunkIndex Zero-based position of this chunk in the stream
     */
    public function __construct(
        public ChunkDelta $chunk,
        public int $chunkIndex,
    ) {
    }
}
