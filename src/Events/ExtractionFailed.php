<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

final class ExtractionFailed
{
    public function __construct(
        public readonly string $modelClass,
        public readonly int $totalAttempts,
        public readonly string $lastError,
    ) {}
}
