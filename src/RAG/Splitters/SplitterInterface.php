<?php

declare(strict_types=1);

namespace LLMesh\Core\RAG\Splitters;

use LLMesh\Core\RAG\Document;

/**
 * Contract for document splitters.
 *
 * A splitter breaks a single large Document into multiple smaller chunks that
 * can be individually embedded and stored in a vector database.
 */
interface SplitterInterface
{
    /**
     * Split a document into an ordered array of smaller Documents.
     *
     * Each chunk inherits the parent's metadata, augmented with:
     *  - `chunk_index` (int)    — 0-based position within the parent
     *  - `parent_id`  (string) — the original document's id
     *
     * @param  Document $document The source document to split
     * @return Document[]         Ordered list of chunks (may be the original if short enough)
     */
    public function split(Document $document): array;
}
