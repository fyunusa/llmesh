<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

use LLMesh\Core\Generators\TextResponse;

/**
 * Event dispatched when text generation completes successfully.
 */
final readonly class GenerationCompleted
{
    /**
     * @param string $provider Provider name
     * @param TextResponse $response Generated response
     * @param int $durationMs Duration in milliseconds
     */
    public function __construct(
        public string $provider,
        public TextResponse $response,
        public int $durationMs,
    ) {
    }
}
