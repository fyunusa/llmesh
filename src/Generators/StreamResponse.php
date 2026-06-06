<?php

declare(strict_types=1);

namespace LLMesh\Core\Generators;

use LLMesh\Core\Contracts\StreamInterface;
use LLMesh\Core\Contracts\UsageInterface;
use LLMesh\Core\Data\ChunkDelta;

/**
 * Streaming response that yields ChunkDelta objects one at a time.
 *
 * Design constraints:
 *  - The inner generator is consumed lazily; no chunks are buffered upfront.
 *  - `getUsage()` is only safe to call after the generator is exhausted.
 *  - The Iterator methods delegate directly to the inner generator so the
 *    object itself can be used in a foreach loop without calling getChunks().
 */
final class StreamResponse implements StreamInterface
{
    /** @var UsageInterface|null Populated only after the stream is fully consumed. */
    private UsageInterface|null $usage = null;

    /** @var bool True once the inner generator has been fully consumed. */
    private bool $exhausted = false;

    /**
     * @param \Generator $chunks Generator that yields {@see ChunkDelta} objects
     */
    public function __construct(
        private readonly \Generator $chunks,
    ) {
    }

    // -------------------------------------------------------------------------
    // StreamInterface
    // -------------------------------------------------------------------------

    /**
     * Yield every ChunkDelta from the underlying provider generator.
     *
     * Marks the stream as exhausted once the generator finishes so that
     * `getUsage()` becomes available.
     *
     * @return \Generator<int, ChunkDelta>
     */
    public function getChunks(): \Generator
    {
        yield from $this->chunks;
        $this->exhausted = true;
    }

    /**
     * Write the stream as Server-Sent Events to the current output buffer.
     *
     * Chunks are flushed one at a time — the full response is never buffered.
     * Sets:
     *   Content-Type: text/event-stream
     *   Cache-Control: no-cache
     *   X-Accel-Buffering: no
     *
     * @throws \LogicException If output has already started (headers sent)
     */
    public function toSSE(): void
    {
        if (headers_sent($file, $line)) {
            throw new \LogicException(
                "Cannot send SSE headers: output already started in {$file} on line {$line}"
            );
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        foreach ($this->getChunks() as $chunk) {
            echo 'data: ' . json_encode($chunk->toArray(), JSON_THROW_ON_ERROR) . "\n\n";
            flush();
        }

        echo "data: [DONE]\n\n";
        flush();
    }

    /**
     * Return usage information.
     *
     * Only available after the stream has been completely consumed via
     * `getChunks()`, `toText()`, `pipe()`, or iteration.
     *
     * @throws \LLMesh\Core\Exceptions\StreamNotExhaustedException If the stream is not yet exhausted.
     */
    public function getUsage(): UsageInterface
    {
        if (!$this->exhausted) {
            throw new \LLMesh\Core\Exceptions\StreamNotExhaustedException(
                'Cannot get usage before stream is exhausted. '
                . 'Consume all chunks first (e.g. via toText() or a foreach loop).'
            );
        }

        return $this->usage ?? new Usage(0, 0);
    }

    /**
     * Pass each ChunkDelta to the provided callback.
     *
     * Useful for custom output handling (e.g. writing to a socket, logging).
     *
     * @param \Closure(ChunkDelta): void $callback
     */
    public function pipe(\Closure $callback): void
    {
        foreach ($this->getChunks() as $chunk) {
            $callback($chunk);
        }
    }

    /**
     * Consume the entire stream and return the concatenated text content.
     *
     * Chunks whose `text` property is `null` (e.g. finish-reason chunks or
     * tool-call chunks) are silently skipped.
     */
    public function toText(): string
    {
        $text = '';
        foreach ($this->getChunks() as $chunk) {
            if ($chunk->text !== null) {
                $text .= $chunk->text;
            }
        }

        return $text;
    }

    // -------------------------------------------------------------------------
    // Iterator (delegates to the inner generator)
    // -------------------------------------------------------------------------

    public function current(): mixed
    {
        return $this->chunks->current();
    }

    public function key(): mixed
    {
        return $this->chunks->key();
    }

    public function next(): void
    {
        $this->chunks->next();
    }

    public function rewind(): void
    {
        // Generators cannot be rewound after they have started.
        // Calling rewind() on a started generator throws; swallow it here
        // to satisfy the Iterator contract without breaking callers.
        try {
            $this->chunks->rewind();
        } catch (\Exception) {
            // Already started — nothing to do.
        }
    }

    public function valid(): bool
    {
        $valid = $this->chunks->valid();
        if (!$valid) {
            $this->exhausted = true;
        }
        return $valid;
    }
}
