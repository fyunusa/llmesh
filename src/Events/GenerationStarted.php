<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

use LLMesh\Core\Generators\GenerateTextOptions;

/**
 * Event dispatched when text generation starts.
 */
final readonly class GenerationStarted
{
    /**
     * @param string $provider Provider name
     * @param GenerateTextOptions $options Generation options snapshot
     */
    public function __construct(
        public string $provider,
        public GenerateTextOptions $options,
    ) {
    }
}
