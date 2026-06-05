<?php

declare(strict_types=1);

namespace LLMesh\Core\Contracts;

use Iterator;

/**
 * Interface for streaming text generation responses.
 *
 * Implements Iterator to allow iteration over chunks.
 */
interface StreamInterface extends Iterator
{
    /**
     * Get the generator of chunks.
     *
     * @return \Generator Generator yielding ChunkDelta objects
     */
    public function getChunks(): \Generator;

    /**
     * Write stream as Server-Sent Events (SSE) to stdout.
     *
     * Sets appropriate headers and streams chunks with proper SSE formatting.
     *
     * @return void
     *
     * @throws \LogicException If output has already been started
     */
    public function toSSE(): void;

    /**
     * Get usage information for the stream.
     *
     * Only available after the stream is completely consumed.
     *
     * @return UsageInterface Token usage and cost information
     *
     * @throws \LogicException If stream is not yet exhausted
     */
    public function getUsage(): UsageInterface;
}
