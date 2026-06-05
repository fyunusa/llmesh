<?php

declare(strict_types=1);

namespace LLMesh\Core\Contracts;

/**
 * Interface for embedding responses.
 */
interface EmbeddingResponseInterface
{
    /**
     * Get the embedding vector.
     *
     * @return array<int, float> Array of float values representing the embedding
     */
    public function getEmbedding(): array;

    /**
     * Get the dimensionality of the embedding.
     *
     * @return int Number of dimensions in the embedding vector
     */
    public function getDimensions(): int;

    /**
     * Get usage information for the embedding request.
     *
     * @return UsageInterface Token usage and cost information
     */
    public function getUsage(): UsageInterface;
}
