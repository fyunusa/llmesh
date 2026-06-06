<?php

declare(strict_types=1);

namespace LLMesh\Core\RAG\Loaders;

use LLMesh\Core\RAG\Document;

/**
 * Contract for document loaders.
 *
 * A loader is responsible for reading raw content and returning an array of
 * `Document` objects ready to enter the RAG pipeline.
 */
interface LoaderInterface
{
    /**
     * Load documents from the configured source.
     *
     * @return Document[]
     *
     * @throws \RuntimeException When the source cannot be read
     */
    public function load(): array;
}
