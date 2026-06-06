<?php

declare(strict_types=1);

namespace LLMesh\Core\RAG\Loaders;

use LLMesh\Core\RAG\Document;

/**
 * Wraps an in-memory array of raw strings as Documents.
 *
 * Primarily used for testing and quick prototyping.
 *
 * @example
 *   $loader = new ArrayLoader(['Hello world', 'Foo bar']);
 *   [$docA, $docB] = $loader->load();
 */
final class ArrayLoader implements LoaderInterface
{
    /**
     * @param string[]  $texts    Raw text strings to wrap as Documents
     * @param array[]   $metadata Optional per-text metadata arrays (indexed to match $texts)
     */
    public function __construct(
        private readonly array $texts,
        private readonly array $metadata = [],
    ) {
    }

    /** {@inheritDoc} */
    public function load(): array
    {
        $documents = [];

        foreach (array_values($this->texts) as $index => $text) {
            $meta        = $this->metadata[$index] ?? [];
            $documents[] = Document::fromText($text, $meta);
        }

        return $documents;
    }
}
