<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

use Throwable;

/**
 * Event dispatched when text generation fails.
 */
final class GenerationFailed
{
    /**
     * @param string $provider Provider name
     * @param Throwable $exception The exception that occurred
     */
    public function __construct(
        public readonly string $provider,
        public readonly Throwable $exception,
    ) {
    }
}
