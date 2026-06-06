<?php

declare(strict_types=1);

namespace LLMesh\Core\Observability;

use LLMesh\Core\Contracts\StreamInterface;
use LLMesh\Core\Contracts\UsageInterface;

/**
 * Decorates a StreamInterface to record usage in a UsageTracker once the stream is exhausted.
 */
final class CostTrackingStream implements StreamInterface
{
    private bool $recorded = false;

    public function __construct(
        private readonly StreamInterface $stream,
        private readonly UsageTracker $tracker,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getChunks(): \Generator
    {
        foreach ($this->stream->getChunks() as $chunk) {
            yield $chunk;
        }
        $this->recordUsage();
    }

    /**
     * {@inheritDoc}
     */
    public function toSSE(): void
    {
        try {
            $this->stream->toSSE();
        } finally {
            $this->recordUsage();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getUsage(): UsageInterface
    {
        $usage = $this->stream->getUsage();
        $this->recordUsage();
        return $usage;
    }

    /**
     * Records the stream usage to the tracker.
     */
    private function recordUsage(): void
    {
        if ($this->recorded) {
            return;
        }

        try {
            $usage = $this->stream->getUsage();
            $this->tracker->record($usage);
            $this->recorded = true;
        } catch (\LogicException) {
            // Stream not yet exhausted, cannot record usage yet.
        }
    }

    // =========================================================================
    // Iterator Implementation (Delegates to the inner stream)
    // =========================================================================

    public function current(): mixed
    {
        return $this->stream->current();
    }

    public function next(): void
    {
        $this->stream->next();
    }

    public function key(): mixed
    {
        return $this->stream->key();
    }

    public function valid(): bool
    {
        $valid = $this->stream->valid();
        if (!$valid) {
            $this->recordUsage();
        }
        return $valid;
    }

    public function rewind(): void
    {
        $this->stream->rewind();
    }
}
