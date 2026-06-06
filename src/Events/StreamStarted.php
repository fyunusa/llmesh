<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

use LLMesh\Core\Generators\GenerateTextOptions;

/**
 * Dispatched immediately before a streaming generation begins.
 *
 * Carries a snapshot of the options so listeners can log or modify
 * pipeline state before the first chunk arrives.
 */
final class StreamStarted
{
    /**
     * @param string             $provider Short provider name (e.g. "Anthropic", "OpenAI")
     * @param GenerateTextOptions $options  Immutable snapshot of the generation options
     */
    public function __construct(
        public readonly string $provider,
        public readonly GenerateTextOptions $options,
    ) {
    }
}
