<?php

declare(strict_types=1);

namespace LLMesh\Core\Embeddings;

use LLMesh\Core\Contracts\EmbeddingResponseInterface;
use LLMesh\Core\Contracts\UsageInterface;

/**
 * Embedding response containing a vector and metadata.
 *
 * @psalm-immutable
 */
final readonly class EmbeddingResponse implements EmbeddingResponseInterface
{
    /**
     * @param array $embedding Float array of embeddings
     * @param int $dimensions Number of dimensions in the embedding
     * @param UsageInterface $usage Token usage information
     * @param string $model Model used to generate embedding
     */
    public function __construct(
        public array $embedding,
        public int $dimensions,
        public UsageInterface $usage,
        public string $model,
    ) {
    }

    public function getEmbedding(): array
    {
        return $this->embedding;
    }

    public function getDimensions(): int
    {
        return $this->dimensions;
    }

    public function getUsage(): UsageInterface
    {
        return $this->usage;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'embedding' => $this->embedding,
            'dimensions' => $this->dimensions,
            'usage' => $this->usage->toArray(),
            'model' => $this->model,
        ];
    }
}
