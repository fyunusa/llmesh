<?php

declare(strict_types=1);

namespace LLMesh\Core\Contracts;

/**
 * Interface for vector storage and similarity search.
 *
 * Implementations can use in-memory storage, databases with vector extensions, etc.
 */
interface VectorStoreInterface
{
    /**
     * Store or update a vector with associated metadata.
     *
     * @param string $id Unique identifier for the vector
     * @param array $vector Vector as array of floats
     * @param array $metadata Optional metadata to store with the vector
     *
     * @return void
     *
     * @throws \LLMesh\Core\Exceptions\LLMeshException On storage errors
     */
    public function upsert(string $id, array $vector, array $metadata = []): void;

    /**
     * Query for similar vectors.
     *
     * @param array $vector Query vector as array of floats
     * @param int $topK Number of top results to return
     * @param array $filter Optional metadata filter criteria
     *
     * @return array Array of results with 'id', 'score', and 'metadata' keys
     *
     * @throws \LLMesh\Core\Exceptions\LLMeshException On search errors
     */
    public function query(array $vector, int $topK = 5, array $filter = []): array;

    /**
     * Delete a vector from storage.
     *
     * @param string $id Unique identifier of the vector to delete
     *
     * @return void
     *
     * @throws \LLMesh\Core\Exceptions\LLMeshException On deletion errors
     */
    public function delete(string $id): void;
}
