<?php

declare(strict_types=1);

namespace LLMesh\Core\RAG\Splitters;

use LLMesh\Core\RAG\Document;

/**
 * Splits documents into fixed-size character chunks with configurable overlap.
 *
 * The splitter tries separators in order from largest to smallest granularity:
 *  1. Double newline (`\n\n`) — paragraph boundary
 *  2. Single newline (`\n`)   — line boundary
 *  3. Period + space (`. `)   — sentence boundary
 *  4. Space (` `)             — word boundary
 *  5. Empty string (`''`)     — character-level fallback
 *
 * The algorithm is "recursive" in that it tries each separator in turn and only
 * falls to the next finer separator when a piece is still longer than `$chunkSize`.
 *
 * **Overlap**: the last `$overlap` characters of each chunk are prepended to the
 * next chunk so context is not lost at boundaries.
 *
 * @example
 *   $splitter = new RecursiveCharacterSplitter(chunkSize: 512, overlap: 50);
 *   $chunks   = $splitter->split($document);
 */
final class RecursiveCharacterSplitter implements SplitterInterface
{
    /** @var string[] Separator hierarchy, tried in order */
    private const SEPARATORS = ["\n\n", "\n", ". ", " ", ""];

    /**
     * @param int $chunkSize Target maximum character length for each chunk (soft limit)
     * @param int $overlap   Number of trailing characters from the previous chunk
     *                       to prepend to the next chunk (0 = no overlap)
     */
    public function __construct(
        private readonly int $chunkSize = 512,
        private readonly int $overlap = 50,
    ) {
    }

    /** {@inheritDoc} */
    public function split(Document $document): array
    {
        $pieces = $this->recursiveSplit($document->content, self::SEPARATORS);

        // Merge small pieces into chunks of up to $chunkSize characters
        $chunks     = $this->mergeWithOverlap($pieces);
        $chunkDocs  = [];

        foreach ($chunks as $index => $chunkText) {
            $chunkDocs[] = Document::fromText(
                $chunkText,
                array_merge($document->metadata, [
                    'parent_id'   => $document->id,
                    'chunk_index' => $index,
                    'chunk_size'  => strlen($chunkText),
                ]),
            );
        }

        return $chunkDocs;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Recursively split text using separators in priority order.
     *
     * @param  string   $text       Text to split
     * @param  string[] $separators Remaining separators to try (pop from front)
     * @return string[]             Array of small text pieces (may still exceed chunkSize)
     */
    private function recursiveSplit(string $text, array $separators): array
    {
        if ($text === '') {
            return [];
        }

        // If text already fits, no splitting needed
        if (mb_strlen($text) <= $this->chunkSize) {
            return [$text];
        }

        if (empty($separators)) {
            // Character-level: hard-cut every $chunkSize chars
            return str_split($text, $this->chunkSize) ?: [$text];
        }

        $separator          = $separators[0];
        $remainingSeparators = array_slice($separators, 1);

        if ($separator === '') {
            // Last resort: character-level cut
            return str_split($text, $this->chunkSize) ?: [$text];
        }

        // Try splitting on this separator
        $pieces  = explode($separator, $text);
        $result  = [];

        foreach ($pieces as $piece) {
            $piece = trim($piece);
            if ($piece === '') {
                continue;
            }

            if (mb_strlen($piece) > $this->chunkSize) {
                // This piece is still too large — recurse with next separator
                $subPieces = $this->recursiveSplit($piece, $remainingSeparators);
                $result    = array_merge($result, $subPieces);
            } else {
                $result[] = $piece;
            }
        }

        return $result;
    }

    /**
     * Merge pieces greedily into chunks no larger than `$chunkSize`, adding
     * `$overlap` characters from the end of each chunk to the start of the next.
     *
     * @param  string[] $pieces
     * @return string[]
     */
    private function mergeWithOverlap(array $pieces): array
    {
        if (empty($pieces)) {
            return [];
        }

        $chunks     = [];
        $current    = '';
        $overlapBuf = '';

        foreach ($pieces as $piece) {
            // If adding this piece would exceed the limit, flush the current chunk
            $candidate = $current !== '' ? $current . ' ' . $piece : $piece;

            if ($current !== '' && mb_strlen($candidate) > $this->chunkSize) {
                $chunks[]   = $current;
                if ($this->overlap <= 0) {
                    $overlapBuf = '';
                } else {
                    $overlapBuf = mb_strlen($current) > $this->overlap
                        ? mb_substr($current, -$this->overlap)
                        : $current;
                }
                $current    = $overlapBuf !== '' ? $overlapBuf . ' ' . $piece : $piece;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
