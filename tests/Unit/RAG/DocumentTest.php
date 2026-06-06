<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\RAG;

use LLMesh\Core\RAG\Document;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\RAG\Document
 */
final class DocumentTest extends TestCase
{
    public function testFromTextCreatesDocumentWithAutoUuid(): void
    {
        $doc = Document::fromText('Hello world');

        $this->assertSame('Hello world', $doc->content);
        $this->assertNotEmpty($doc->id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $doc->id,
        );
    }

    public function testFromTextPreservesMetadata(): void
    {
        $doc = Document::fromText('content', ['source' => 'test.txt']);
        $this->assertSame('test.txt', $doc->metadata['source']);
    }

    public function testWithEmbeddingReturnsNewInstance(): void
    {
        $doc      = Document::fromText('content');
        $embedded = $doc->withEmbedding([0.1, 0.2, 0.3]);

        $this->assertNotSame($doc, $embedded);
        $this->assertNull($doc->embedding);
        $this->assertSame([0.1, 0.2, 0.3], $embedded->embedding);
    }

    public function testTwoDocumentsHaveDifferentIds(): void
    {
        $a = Document::fromText('text');
        $b = Document::fromText('text');
        $this->assertNotSame($a->id, $b->id);
    }
}
