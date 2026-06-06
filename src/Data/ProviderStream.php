<?php

declare(strict_types=1);

namespace LLMesh\Core\Data;

use LLMesh\Core\Contracts\StreamInterface;
use LLMesh\Core\Contracts\UsageInterface;

/**
 * A simple implementation of StreamInterface for provider boundary responses.
 */
class ProviderStream implements StreamInterface
{
    private \Generator $generator;
    private ?UsageInterface $usage;

    public function __construct(\Generator $generator, ?UsageInterface $usage = null)
    {
        $this->generator = $generator;
        $this->usage = $usage;
    }

    public function getChunks(): \Generator
    {
        return $this->generator;
    }

    public function toSSE(): void
    {
        foreach ($this->generator as $chunk) {
            echo "data: " . json_encode($chunk->toArray()) . "\n\n";
            flush();
        }
    }

    public function getUsage(): UsageInterface
    {
        if ($this->usage === null) {
            throw new \LLMesh\Core\Exceptions\StreamNotExhaustedException('Usage not available.');
        }
        return $this->usage;
    }

    // Iterator interface
    public function current(): mixed
    {
        return $this->generator->current();
    }

    public function next(): void
    {
        $this->generator->next();
    }

    public function key(): mixed
    {
        return $this->generator->key();
    }

    public function valid(): bool
    {
        return $this->generator->valid();
    }

    public function rewind(): void
    {
        try {
            $this->generator->rewind();
        } catch (\Exception) {
            // No-op
        }
    }
}
