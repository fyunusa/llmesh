<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

final class ExtractionStarted
{
    public function __construct(
        public readonly string $modelClass,
        public readonly string $providerName,
        public readonly int $inputLength,   // character count of input text
    ) {
    }
}
