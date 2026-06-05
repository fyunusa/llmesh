<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

use LLMesh\Core\Generators\ObjectResponse;

/**
 * Dispatched when `LLMesh::generateObject()` completes successfully.
 */
final readonly class ObjectGenerationCompleted
{
    /**
     * @param string         $provider   Short provider name
     * @param ObjectResponse $response   The validated object response
     * @param int            $durationMs Wall-clock duration in milliseconds
     */
    public function __construct(
        public string $provider,
        public ObjectResponse $response,
        public int $durationMs,
    ) {
    }
}
