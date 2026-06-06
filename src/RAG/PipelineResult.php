<?php

declare(strict_types=1);

namespace LLMesh\Core\RAG;

/**
 * Immutable statistics from a single `Pipeline::run()` execution.
 */
final readonly class PipelineResult
{
    /**
     * @param int $documentsLoaded  Number of raw documents returned by the loader
     * @param int $chunksCreated    Number of chunks produced after splitting
     * @param int $chunksStored     Number of chunks successfully stored in the vector store
     * @param int $durationMs       Total wall-clock time for the run, in milliseconds
     * @param int $totalTokensUsed  Aggregate input tokens consumed across all embed calls
     */
    public function __construct(
        public int $documentsLoaded,
        public int $chunksCreated,
        public int $chunksStored,
        public int $durationMs,
        public int $totalTokensUsed,
    ) {
    }

    /**
     * Serialize to array for logging / audit trail.
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'documents_loaded'  => $this->documentsLoaded,
            'chunks_created'    => $this->chunksCreated,
            'chunks_stored'     => $this->chunksStored,
            'duration_ms'       => $this->durationMs,
            'total_tokens_used' => $this->totalTokensUsed,
        ];
    }
}
