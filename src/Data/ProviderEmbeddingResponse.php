<?php

declare(strict_types=1);

namespace LLMesh\Core\Data;

use LLMesh\Core\Contracts\EmbeddingResponseInterface;
use LLMesh\Core\Contracts\UsageInterface;
use LLMesh\Core\Generators\Usage;

/**
 * Decoupled data object representing the raw embedding response from a provider.
 */
final class ProviderEmbeddingResponse implements EmbeddingResponseInterface
{
    private readonly UsageInterface $usage;
    private readonly int $dimensions;

    /**
     * @param float[] $embedding
     * @param int $inputTokens
     * @param int $outputTokens
     */
    public function __construct(
        private readonly array $embedding,
        int $inputTokens,
        int $outputTokens = 0,
    ) {
        $this->dimensions = count($embedding);
        $this->usage = new Usage($inputTokens, $outputTokens);
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
}
