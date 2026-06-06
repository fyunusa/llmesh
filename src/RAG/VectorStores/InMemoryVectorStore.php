<?php

declare(strict_types=1);

namespace LLMesh\Core\RAG\VectorStores;

use LLMesh\Core\Contracts\VectorStoreInterface;
use LLMesh\Core\Exceptions\LLMeshException;

/**
 * In-memory vector store backed by a plain PHP array.
 *
 * ⚠️  **Development and testing only.**  All stored vectors are lost when the
 * PHP process ends.  For production use, choose a persistent backend such as
 * `PgVectorStore` or an external vector database.
 *
 * Similarity search is performed via cosine similarity computed in PHP.
 * Performance degrades linearly with the number of stored vectors — suitable
 * for small datasets (hundreds to low thousands of vectors).
 */
final class InMemoryVectorStore implements VectorStoreInterface
{
    /**
     * Internal storage structure:
     *
     * ```
     * [
     *   'doc-id' => [
     *     'vector'   => float[],
     *     'metadata' => array,
     *   ],
     *   ...
     * ]
     * ```
     *
     * @var array<string, array{vector: float[], metadata: array}>
     */
    private array $store = [];

    // -------------------------------------------------------------------------
    // VectorStoreInterface
    // -------------------------------------------------------------------------

    /** {@inheritDoc} */
    public function upsert(string $id, array $vector, array $metadata = []): void
    {
        $this->store[$id] = [
            'vector'   => $vector,
            'metadata' => $metadata,
        ];
    }

    /**
     * {@inheritDoc}
     *
     * Results are sorted by cosine similarity (descending) and the top `$topK`
     * are returned.  The `$filter` parameter is ignored by this implementation.
     *
     * @return array<int, array{id: string, score: float, metadata: array}>
     */
    public function query(array $vector, int $topK = 5, array $filter = []): array
    {
        if (empty($this->store)) {
            return [];
        }

        $scored = [];

        foreach ($this->store as $id => $entry) {
            $scored[] = [
                'id'       => $id,
                'score'    => $this->cosineSimilarity($vector, $entry['vector']),
                'metadata' => $entry['metadata'],
            ];
        }

        // Sort by score descending
        usort($scored, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $topK);
    }

    /** {@inheritDoc} */
    public function delete(string $id): void
    {
        unset($this->store[$id]);
    }

    // -------------------------------------------------------------------------
    // Accessors (for testing)
    // -------------------------------------------------------------------------

    /**
     * Return the number of vectors currently stored.
     */
    public function count(): int
    {
        return count($this->store);
    }

    /**
     * Check whether a vector with the given id exists.
     */
    public function has(string $id): bool
    {
        return isset($this->store[$id]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Compute cosine similarity between two float vectors.
     *
     * Returns 0.0 when either vector is the zero vector.
     *
     * @param  float[] $a
     * @param  float[] $b
     * @return float   Similarity in the range [-1.0, 1.0]
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dot   = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $len   = min(count($a), count($b));

        for ($i = 0; $i < $len; $i++) {
            $dot   += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $denom = sqrt($normA) * sqrt($normB);

        return $denom === 0.0 ? 0.0 : $dot / $denom;
    }
}
