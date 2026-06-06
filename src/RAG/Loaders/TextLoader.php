<?php

declare(strict_types=1);

namespace LLMesh\Core\RAG\Loaders;

use LLMesh\Core\RAG\Document;

/**
 * Loads a single plain-text file as one Document.
 */
final class TextLoader implements LoaderInterface
{
    /**
     * @param string $path Path to the text file to load
     */
    public function __construct(
        private readonly string $path,
    ) {
    }

    /** {@inheritDoc} */
    public function load(): array
    {
        return [Document::fromFile($this->path)];
    }
}
