<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

use LLMesh\Core\Generators\GenerateObjectOptions;

/**
 * Dispatched immediately before `LLMesh::generateObject()` calls the provider.
 */
final readonly class ObjectGenerationStarted
{
    /**
     * @param string                $provider Short provider name
     * @param GenerateObjectOptions $options  Immutable snapshot of the generation options
     */
    public function __construct(
        public string $provider,
        public GenerateObjectOptions $options,
    ) {
    }
}
