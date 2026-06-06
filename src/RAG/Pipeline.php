<?php

declare(strict_types=1);

namespace LLMesh\Core\RAG;

use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Contracts\VectorStoreInterface;
use LLMesh\Core\Embeddings\EmbeddingGenerator;
use LLMesh\Core\RAG\Loaders\LoaderInterface;
use LLMesh\Core\RAG\Splitters\SplitterInterface;

/**
 * Fluent RAG pipeline builder and executor.
 *
 * **Ingestion flow:**
 * ```
 * load → split → embed → store
 * ```
 *
 * **Retrieval flow:**
 * ```
 * embed query → vector store query → return Documents
 * ```
 *
 * The pipeline is immutable — every `with*` / `on*` / configuration method
 * returns a new instance so the same base pipeline can be reused across runs.
 *
 * **Resumable runs:** because every Document chunk is stored with its
 * deterministic `id` (preserved from the `Document` object), re-running the
 * pipeline will `upsert` the same rows, not create duplicates.
 *
 * @example
 * ```php
 * $pipeline = Pipeline::make()
 *     ->load(new DirectoryLoader('/docs'))
 *     ->split(new RecursiveCharacterSplitter(512, 50))
 *     ->embed($embeddingProvider)
 *     ->store(new PgVectorStore($pdo))
 *     ->onProgress(fn (int $done, int $total) => print("$done/$total\n"));
 *
 * $result = $pipeline->run();
 * $chunks = $pipeline->retrieve('What is LLMesh?', topK: 5);
 * ```
 */
final class Pipeline
{
    private function __construct(
        private readonly ?LoaderInterface    $loader,
        private readonly ?SplitterInterface  $splitter,
        private readonly ?ProviderInterface  $embedProvider,
        private readonly ?VectorStoreInterface $store,
        private readonly array              $embedOptions,
        private readonly ?\Closure          $onProgress,
    ) {
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Create an empty pipeline with no components attached.
     */
    public static function make(): self
    {
        return new self(
            loader:        null,
            splitter:      null,
            embedProvider: null,
            store:         null,
            embedOptions:  [],
            onProgress:    null,
        );
    }

    // -------------------------------------------------------------------------
    // Builder (immutable)
    // -------------------------------------------------------------------------

    public function load(LoaderInterface $loader): self
    {
        return new self($loader, $this->splitter, $this->embedProvider, $this->store, $this->embedOptions, $this->onProgress);
    }

    public function split(SplitterInterface $splitter): self
    {
        return new self($this->loader, $splitter, $this->embedProvider, $this->store, $this->embedOptions, $this->onProgress);
    }

    /**
     * Attach the embedding provider (and optional per-call options).
     *
     * @param ProviderInterface $provider     The provider whose `embed()` is called for each chunk
     * @param array             $embedOptions Extra options forwarded to `EmbeddingGenerator::embed()`
     */
    public function embed(ProviderInterface $provider, array $embedOptions = []): self
    {
        return new self($this->loader, $this->splitter, $provider, $this->store, $embedOptions, $this->onProgress);
    }

    public function store(VectorStoreInterface $store): self
    {
        return new self($this->loader, $this->splitter, $this->embedProvider, $store, $this->embedOptions, $this->onProgress);
    }

    /**
     * Attach a progress callback invoked after each chunk is stored.
     *
     * @param \Closure(int $current, int $total): void $callback
     */
    public function onProgress(\Closure $callback): self
    {
        return new self($this->loader, $this->splitter, $this->embedProvider, $this->store, $this->embedOptions, $callback);
    }

    // -------------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------------

    /**
     * Execute the full ingestion pipeline: load → split → embed → store.
     *
     * All four components (loader, splitter, embed provider, store) must be
     * configured before calling `run()`.
     *
     * The pipeline is **resumable**: re-running will upsert by document id so
     * no duplicate vectors are created.
     *
     * @return PipelineResult Summary statistics for this run
     *
     * @throws \LogicException         When any required component is missing
     * @throws \RuntimeException       When a load / embed / store operation fails
     */
    public function run(): PipelineResult
    {
        $this->assertAllComponentsSet();

        $startMs        = $this->nowMs();
        $totalTokens    = 0;
        $generator      = new EmbeddingGenerator();

        // ── 1. Load ───────────────────────────────────────────────────────────
        /** @var Document[] $documents */
        $documents      = $this->loader->load();
        $documentsLoaded = count($documents);

        // ── 2. Split ──────────────────────────────────────────────────────────
        $chunks = [];
        foreach ($documents as $document) {
            $split  = $this->splitter->split($document);
            $chunks = array_merge($chunks, $split);
        }
        $chunksCreated = count($chunks);

        // ── 3. Embed + 4. Store ───────────────────────────────────────────────
        $chunksStored = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            // Embed
            $embResponse   = $generator->embed($this->embedProvider, $chunk->content, $this->embedOptions);
            $totalTokens  += $embResponse->getUsage()->getInputTokens();
            $embedded      = $chunk->withEmbedding($embResponse->getEmbedding());

            // Store (upsert — safe to re-run)
            $this->store->upsert(
                $embedded->id,
                $embedded->embedding,
                array_merge($embedded->metadata, [
                    'content'    => $embedded->content,
                    'dimensions' => $embResponse->getDimensions(),
                    'model'      => $embResponse->model,
                ]),
            );

            $chunksStored++;

            // Progress callback
            if ($this->onProgress !== null) {
                ($this->onProgress)($chunkIndex + 1, $chunksCreated);
            }
        }

        return new PipelineResult(
            documentsLoaded: $documentsLoaded,
            chunksCreated:   $chunksCreated,
            chunksStored:    $chunksStored,
            durationMs:      $this->nowMs() - $startMs,
            totalTokensUsed: $totalTokens,
        );
    }

    /**
     * Embed the query, search the vector store, and reconstruct `Document` objects
     * from the stored metadata.
     *
     * @param  string $query The user's natural-language query
     * @param  int    $topK  Maximum number of results to return (default 5)
     * @param  array  $filter Optional metadata key-value filter (passed to store)
     * @return Document[]    Ordered by similarity score (highest first)
     *
     * @throws \LogicException When the embed provider or store is not configured
     */
    public function retrieve(string $query, int $topK = 5, array $filter = []): array
    {
        if ($this->embedProvider === null) {
            throw new \LogicException('Pipeline::retrieve() requires an embed provider. Call ->embed($provider) first.');
        }
        if ($this->store === null) {
            throw new \LogicException('Pipeline::retrieve() requires a vector store. Call ->store($store) first.');
        }

        $generator    = new EmbeddingGenerator();
        $queryEmb     = $generator->embed($this->embedProvider, $query, $this->embedOptions);
        $results      = $this->store->query($queryEmb->getEmbedding(), $topK, $filter);

        $documents = [];
        foreach ($results as $result) {
            $metadata  = $result['metadata'];
            $content   = $metadata['content'] ?? '';

            // Strip internal keys from the metadata before returning to caller
            $userMeta  = array_diff_key($metadata, array_flip(['content', 'dimensions', 'model']));

            $documents[] = new Document(
                id:        $result['id'],
                content:   $content,
                metadata:  $userMeta,
                embedding: null, // not needed for retrieved docs
            );
        }

        return $documents;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @throws \LogicException When a required component has not been set
     */
    private function assertAllComponentsSet(): void
    {
        $missing = [];

        if ($this->loader === null)        $missing[] = 'loader (call ->load($loader))';
        if ($this->splitter === null)      $missing[] = 'splitter (call ->split($splitter))';
        if ($this->embedProvider === null) $missing[] = 'embed provider (call ->embed($provider))';
        if ($this->store === null)         $missing[] = 'vector store (call ->store($store))';

        if (!empty($missing)) {
            throw new \LogicException(
                'Pipeline::run() is missing required components: ' . implode(', ', $missing),
            );
        }
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
