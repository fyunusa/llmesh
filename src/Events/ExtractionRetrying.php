<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

final class ExtractionRetrying
{
    public function __construct(
        public readonly string $modelClass,
        public readonly int $attempt,
        public readonly string $errorMessage,
    ) {
    }
}
