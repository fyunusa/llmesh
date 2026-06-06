<?php

declare(strict_types=1);

namespace LLMesh\Core\Embeddings;

use LLMesh\Core\Contracts\EmbeddingResponseInterface;
use LLMesh\Core\Contracts\UsageInterface;
use LLMesh\Core\Generators\Usage;

/**
 * Embedding response containing a vector and metadata.
 *
 * @psalm-immutable
 */
final class EmbeddingResponse implements EmbeddingResponseInterface
{
    /**
     * @param float[]        $embedding  Float vector produced by the model
     * @param int            $dimensions Number of dimensions in the vector
     * @param UsageInterface $usage      Token usage information
     * @param string         $model      Model that produced the embedding
     */
    public function __construct(
        public readonly array $embedding,
        public readonly int $dimensions,
        public readonly UsageInterface $usage,
        public readonly string $model,
    ) {
    }

    // -------------------------------------------------------------------------
    // EmbeddingResponseInterface
    // -------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     * @return float[]
     */
    public function getEmbedding(): array
    {
        return $this->embedding;
    }

    /** {@inheritDoc} */
    public function getDimensions(): int
    {
        return $this->dimensions;
    }

    /** {@inheritDoc} */
    public function getUsage(): UsageInterface
    {
        return $this->usage;
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Build an `EmbeddingResponse` from a raw provider array.
     *
     * The `$parser` callable receives the raw array and must return an
     * associative array with keys:
     *  - `embedding`  float[]   — the vector
     *  - `model`      string    — model name
     *  - `usage`      array     — compatible with `Usage::fromArray()`
     *
     * @param  array    $data   Raw provider response
     * @param  callable $parser Provider-specific response normalizer
     * @return self
     */
    public static function fromArray(array $data, callable $parser): self
    {
        $parsed     = $parser($data);
        $embedding  = $parsed['embedding'] ?? [];
        $model      = $parsed['model']     ?? '';
        $usage      = Usage::fromArray($parsed['usage'] ?? []);

        return new self(
            embedding:  $embedding,
            dimensions: count($embedding),
            usage:      $usage,
            model:      $model,
        );
    }

    // -------------------------------------------------------------------------
    // Vector math
    // -------------------------------------------------------------------------

    /**
     * Compute the cosine similarity between this embedding and another.
     *
     * Returns a value in the range [-1.0, 1.0]:
     *  - 1.0 → identical direction (semantically similar)
     *  - 0.0 → orthogonal (unrelated)
     *  - -1.0 → opposite direction
     *
     * Returns 0.0 when either vector is the zero vector (no division by zero).
     *
     * @param  EmbeddingResponse $other The embedding to compare against
     * @return float Cosine similarity score
     */
    public function cosineSimilarity(self $other): float
    {
        $a = $this->embedding;
        $b = $other->embedding;

        $dot    = 0.0;
        $normA  = 0.0;
        $normB  = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $dot   += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $denom = sqrt($normA) * sqrt($normB);

        if ($denom === 0.0) {
            return 0.0; // guard against zero vectors
        }

        return $dot / $denom;
    }

    // -------------------------------------------------------------------------
    // Serialisation
    // -------------------------------------------------------------------------

    /**
     * Return the raw embedding float array (implements spec `toArray(): float[]`).
     *
     * @return float[]
     */
    public function toArray(): array
    {
        return $this->embedding;
    }

    /**
     * Return a full associative representation for logging / debugging.
     *
     * @return array<string, mixed>
     */
    public function toFullArray(): array
    {
        return [
            'embedding'  => $this->embedding,
            'dimensions' => $this->dimensions,
            'usage'      => $this->usage->toArray(),
            'model'      => $this->model,
        ];
    }
}
