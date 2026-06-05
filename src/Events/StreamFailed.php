<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

/**
 * Dispatched when a streaming generation fails with an exception.
 *
 * Emitted by the wrapping generator inside `LLMesh::streamText()` if the
 * provider throws during chunk iteration, allowing listeners to log or
 * alert on streaming failures separately from non-streaming failures.
 */
final readonly class StreamFailed
{
    /**
     * @param string     $provider  Short provider name (e.g. "Anthropic", "OpenAI")
     * @param \Throwable $exception The exception that caused the failure
     */
    public function __construct(
        public string $provider,
        public \Throwable $exception,
    ) {
    }
}
