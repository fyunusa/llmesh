<?php

declare(strict_types=1);

namespace LLMesh\Core\RAG\Splitters;

use LLMesh\Core\RAG\Document;

/**
 * Splits a document into groups of at most `$maxSentences` sentences per chunk.
 *
 * Sentence boundaries are detected by the pattern: `.`, `!`, or `?` followed
 * by whitespace or end-of-string.  The trailing punctuation is kept attached to
 * its sentence.
 *
 * @example
 *   $splitter = new SentenceSplitter(maxSentences: 5);
 *   $chunks   = $splitter->split($document); // up to 5 sentences per chunk
 */
final class SentenceSplitter implements SplitterInterface
{
    /**
     * @param int $maxSentences Maximum number of sentences per chunk (default 5)
     */
    public function __construct(
        private readonly int $maxSentences = 5,
    ) {
    }

    /** {@inheritDoc} */
    public function split(Document $document): array
    {
        $sentences = $this->extractSentences($document->content);

        if (empty($sentences)) {
            return [$document];
        }

        $batches = array_chunk($sentences, $this->maxSentences);
        $chunks  = [];

        foreach ($batches as $index => $batch) {
            $chunkText = implode(' ', $batch);

            $chunks[] = Document::fromText(
                $chunkText,
                array_merge($document->metadata, [
                    'parent_id'      => $document->id,
                    'chunk_index'    => $index,
                    'sentence_count' => count($batch),
                ]),
            );
        }

        return $chunks;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Tokenise text into individual sentences.
     *
     * Splits on `.`, `!`, or `?` followed by one or more whitespace characters
     * or end-of-string.  The punctuation is retained with each sentence.
     *
     * @param  string   $text
     * @return string[]
     */
    private function extractSentences(string $text): array
    {
        // Split keeping the delimiter (punctuation) attached to the preceding token
        $parts     = preg_split('/(?<=[.!?])\s+/', trim($text), flags: PREG_SPLIT_NO_EMPTY);
        $sentences = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $sentences[] = $part;
            }
        }

        return $sentences;
    }
}
