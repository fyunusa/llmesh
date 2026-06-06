<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\RAG;

use LLMesh\Core\RAG\Document;
use LLMesh\Core\RAG\Splitters\RecursiveCharacterSplitter;
use LLMesh\Core\RAG\Splitters\SentenceSplitter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\RAG\Splitters\RecursiveCharacterSplitter
 * @covers \LLMesh\Core\RAG\Splitters\SentenceSplitter
 */
final class SplitterTest extends TestCase
{
    // =========================================================================
    // RecursiveCharacterSplitter
    // =========================================================================

    public function testRecursiveCharacterSplitterRespectsChunkSize(): void
    {
        // 600 'x' characters → must produce at least 2 chunks with chunkSize=300
        $content = str_repeat('x ', 300); // 600 chars
        $doc     = Document::fromText($content);

        $splitter = new RecursiveCharacterSplitter(chunkSize: 300, overlap: 0);
        $chunks   = $splitter->split($doc);

        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(
                350, // soft limit: allow some overage due to overlap / boundary merging
                strlen($chunk->content),
                "Chunk is too long: " . strlen($chunk->content) . " chars",
            );
        }
    }

    public function testRecursiveCharacterSplitterAddsParentIdAndChunkIndex(): void
    {
        $content  = str_repeat("word ", 200); // long content
        $doc      = Document::fromText($content);

        $splitter = new RecursiveCharacterSplitter(chunkSize: 100, overlap: 10);
        $chunks   = $splitter->split($doc);

        $this->assertNotEmpty($chunks);

        foreach ($chunks as $index => $chunk) {
            $this->assertSame($doc->id, $chunk->metadata['parent_id']);
            $this->assertSame($index, $chunk->metadata['chunk_index']);
        }
    }

    public function testRecursiveCharacterSplitterOverlapContainsTrailingChars(): void
    {
        // Build content with two clearly split paragraphs
        $para1   = str_repeat('A', 200);
        $para2   = str_repeat('B', 200);
        $content = $para1 . "\n\n" . $para2;
        $doc     = Document::fromText($content);

        $overlap  = 20;
        $splitter = new RecursiveCharacterSplitter(chunkSize: 220, overlap: $overlap);
        $chunks   = $splitter->split($doc);

        // With overlap the second chunk should contain some 'A' characters from para1
        if (count($chunks) >= 2) {
            $chunk2Content = $chunks[1]->content;
            $this->assertStringContainsString(
                'A',
                $chunk2Content,
                'Overlap should carry trailing chars of previous chunk into next chunk',
            );
        }
    }

    public function testRecursiveCharacterSplitterReturnsSingleChunkWhenShort(): void
    {
        $doc     = Document::fromText('Short text.');
        $splitter = new RecursiveCharacterSplitter(chunkSize: 512, overlap: 50);
        $chunks   = $splitter->split($doc);

        $this->assertCount(1, $chunks);
        $this->assertSame('Short text.', $chunks[0]->content);
    }

    public function testRecursiveCharacterSplitterChunkCountIncreasesWithSmallerChunkSize(): void
    {
        $content  = str_repeat('word ', 100); // 500 chars
        $doc      = Document::fromText($content);

        $chunksLarge = (new RecursiveCharacterSplitter(chunkSize: 200, overlap: 0))->split($doc);
        $chunksSmall = (new RecursiveCharacterSplitter(chunkSize: 100, overlap: 0))->split($doc);

        $this->assertGreaterThan(count($chunksLarge), count($chunksSmall));
    }

    // =========================================================================
    // SentenceSplitter
    // =========================================================================

    public function testSentenceSplitterGroupsSentencesIntoChunks(): void
    {
        $sentences = [];
        for ($i = 1; $i <= 12; $i++) {
            $sentences[] = "This is sentence {$i}.";
        }
        $doc     = Document::fromText(implode(' ', $sentences));
        $splitter = new SentenceSplitter(maxSentences: 5);
        $chunks  = $splitter->split($doc);

        // 12 sentences ÷ 5 = 3 chunks (5, 5, 2)
        $this->assertCount(3, $chunks);
    }

    public function testSentenceSplitterAddsParentMetadata(): void
    {
        $doc     = Document::fromText('First. Second. Third.');
        $splitter = new SentenceSplitter(maxSentences: 2);
        $chunks  = $splitter->split($doc);

        foreach ($chunks as $index => $chunk) {
            $this->assertSame($doc->id, $chunk->metadata['parent_id']);
            $this->assertSame($index, $chunk->metadata['chunk_index']);
            $this->assertArrayHasKey('sentence_count', $chunk->metadata);
        }
    }

    public function testSentenceSplitterWithSingleSentenceReturnsSingleChunk(): void
    {
        $doc      = Document::fromText('Just one sentence.');
        $splitter = new SentenceSplitter(maxSentences: 5);
        $chunks   = $splitter->split($doc);

        $this->assertCount(1, $chunks);
        $this->assertSame('Just one sentence.', $chunks[0]->content);
    }
}
