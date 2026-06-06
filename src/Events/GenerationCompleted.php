<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

use LLMesh\Core\Generators\TextResponse;

/**
 * Event dispatched when text generation completes successfully.
 */
final class GenerationCompleted
{
    /**
     * @param string $provider Provider name
     * @param TextResponse $response Generated response
     * @param int $durationMs Duration in milliseconds
     */
    public function __construct(
        public readonly string $provider,
        public readonly TextResponse $response,
        public readonly int $durationMs,
    ) {
    }
}
