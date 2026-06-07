<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

use LLMesh\Core\Structured\LLMModel;

final class ExtractionCompleted
{
    public function __construct(
        public readonly string $modelClass,
        public readonly LLMModel $result,
        public readonly int $attemptsUsed,
        public readonly int $durationMs,
    ) {
    }
}
