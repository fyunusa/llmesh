<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\RAG;

use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Contracts\VectorStoreInterface;
use LLMesh\Core\Embeddings\EmbeddingResponse;
use LLMesh\Core\Generators\Usage;
use LLMesh\Core\LLMesh;
use LLMesh\Core\RAG\Document;
use LLMesh\Core\RAG\Loaders\LoaderInterface;
use LLMesh\Core\RAG\Pipeline;
use LLMesh\Core\RAG\Splitters\SplitterInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\RAG\Pipeline
 * @covers \LLMesh\Core\RAG\PipelineResult
 */
final class PipelineTest extends TestCase
{
    public function testPipelineIsFluentAndImmutable(): void
    {
        $p1 = Pipeline::make();
        $this->assertInstanceOf(Pipeline::class, $p1);

        $loader = $this->createMock(LoaderInterface::class);
        $p2 = $p1->load($loader);
        $this->assertNotSame($p1, $p2);

        $splitter = $this->createMock(SplitterInterface::class);
        $p3 = $p2->split($splitter);
        $this->assertNotSame($p2, $p3);

        $provider = $this->createMock(ProviderInterface::class);
        $p4 = $p3->embed($provider);
        $this->assertNotSame($p3, $p4);

        $store = $this->createMock(VectorStoreInterface::class);
        $p5 = $p4->store($store);
        $this->assertNotSame($p4, $p5);

        $callback = function (int $current, int $total): void {
        };
        $p6 = $p5->onProgress($callback);
        $this->assertNotSame($p5, $p6);
    }

    public function testRunThrowsLogicExceptionWhenComponentsAreMissing(): void
    {
        $pipeline = Pipeline::make();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Pipeline::run() is missing required components: loader (call ->load($loader)), splitter (call ->split($splitter)), embed provider (call ->embed($provider)), vector store (call ->store($store))');

        $pipeline->run();
    }

    public function testRunExecutesEndToEndPipeline(): void
    {
        // 1. Mock Loader
        $doc = Document::fromText('Original Doc Text', ['doc_meta' => 'val']);
        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->once())
            ->method('load')
            ->willReturn([$doc]);

        // 2. Mock Splitter
        $chunk1 = Document::fromText('Chunk 1', ['parent_id' => $doc->id, 'chunk_index' => 0]);
        $chunk2 = Document::fromText('Chunk 2', ['parent_id' => $doc->id, 'chunk_index' => 1]);
        $splitter = $this->createMock(SplitterInterface::class);
        $splitter->expects($this->once())
            ->method('split')
            ->with($doc)
            ->willReturn([$chunk1, $chunk2]);

        // 3. Mock Provider for Embedding
        $provider = $this->createMock(ProviderInterface::class);
        $response1 = new EmbeddingResponse([0.1, 0.2], 2, new Usage(5, 0), 'test-model');
        $response2 = new EmbeddingResponse([0.3, 0.4], 2, new Usage(10, 0), 'test-model');

        $provider->expects($this->exactly(2))
            ->method('embed')
            ->willReturnOnConsecutiveCalls($response1, $response2);

        $store = $this->createMock(VectorStoreInterface::class);
        $upserted = [];
        $store->expects($this->exactly(2))
            ->method('upsert')
            ->willReturnCallback(function (string $id, array $vector, array $metadata) use (&$upserted) {
                $upserted[] = [$id, $vector, $metadata];
            });

        // Progress tracking
        $progressCalls = [];
        $pipeline = Pipeline::make()
            ->load($loader)
            ->split($splitter)
            ->embed($provider)
            ->store($store)
            ->onProgress(function (int $current, int $total) use (&$progressCalls) {
                $progressCalls[] = [$current, $total];
            });

        $result = $pipeline->run();

        // Assert store interactions
        $this->assertCount(2, $upserted);
        $this->assertSame($chunk1->id, $upserted[0][0]);
        $this->assertSame([0.1, 0.2], $upserted[0][1]);
        $this->assertSame([
            'parent_id' => $doc->id,
            'chunk_index' => 0,
            'content' => 'Chunk 1',
            'dimensions' => 2,
            'model' => 'test-model',
        ], $upserted[0][2]);

        $this->assertSame($chunk2->id, $upserted[1][0]);
        $this->assertSame([0.3, 0.4], $upserted[1][1]);
        $this->assertSame([
            'parent_id' => $doc->id,
            'chunk_index' => 1,
            'content' => 'Chunk 2',
            'dimensions' => 2,
            'model' => 'test-model',
        ], $upserted[1][2]);

        // Assert stats
        $this->assertSame(1, $result->documentsLoaded);
        $this->assertSame(2, $result->chunksCreated);
        $this->assertSame(2, $result->chunksStored);
        $this->assertSame(15, $result->totalTokensUsed);
        $this->assertGreaterThanOrEqual(0, $result->durationMs);

        // Assert progress callbacks
        $this->assertCount(2, $progressCalls);
        $this->assertSame([1, 2], $progressCalls[0]);
        $this->assertSame([2, 2], $progressCalls[1]);

        // Assert toArray formatting
        $arrayResult = $result->toArray();
        $this->assertSame(1, $arrayResult['documents_loaded']);
        $this->assertSame(2, $arrayResult['chunks_created']);
        $this->assertSame(2, $arrayResult['chunks_stored']);
    }

    public function testRetrieveExecutesQuerySearchAndReconstructsDocuments(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('embed')
            ->with('Search query', [])
            ->willReturn(new EmbeddingResponse([0.9, 0.9], 2, new Usage(2, 0), 'test-model'));

        $store = $this->createMock(VectorStoreInterface::class);
        $store->expects($this->once())
            ->method('query')
            ->with([0.9, 0.9], 5, [])
            ->willReturn([
                [
                    'id' => 'doc-1',
                    'score' => 0.95,
                    'metadata' => [
                        'content' => 'Reconstructed content 1',
                        'dimensions' => 2,
                        'model' => 'test-model',
                        'custom_key' => 'custom_val',
                    ],
                ]
            ]);

        $pipeline = Pipeline::make()
            ->embed($provider)
            ->store($store);

        $results = $pipeline->retrieve('Search query', 5);

        $this->assertCount(1, $results);
        $doc = $results[0];
        $this->assertSame('doc-1', $doc->id);
        $this->assertSame('Reconstructed content 1', $doc->content);
        // Assert internal keys are stripped from public metadata
        $this->assertSame(['custom_key' => 'custom_val'], $doc->metadata);
        $this->assertNull($doc->embedding);
    }

    public function testRetrieveThrowsExceptionWhenEmbedProviderOrStoreIsMissing(): void
    {
        $pipeline = Pipeline::make();

        // 1. Missing embed provider
        try {
            $pipeline->retrieve('query');
            $this->fail('Expected LogicException not thrown');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('Pipeline::retrieve() requires an embed provider', $e->getMessage());
        }

        // 2. Missing store
        $provider = $this->createMock(ProviderInterface::class);
        $pipeline = $pipeline->embed($provider);

        try {
            $pipeline->retrieve('query');
            $this->fail('Expected LogicException not thrown');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('Pipeline::retrieve() requires a vector store', $e->getMessage());
        }
    }

    public function testLLMeshPipelineHelper(): void
    {
        $pipeline = LLMesh::pipeline();
        $this->assertInstanceOf(Pipeline::class, $pipeline);
    }

    public function testIntegrationWithInMemoryVectorStoreAndMockedEmbeddingProvider(): void
    {
        $loader = new \LLMesh\Core\RAG\Loaders\ArrayLoader(['Integration Test text content']);
        $splitter = new \LLMesh\Core\RAG\Splitters\RecursiveCharacterSplitter(50, 10);

        $provider = $this->createMock(ProviderInterface::class);
        $response = new EmbeddingResponse([0.5, 0.5], 2, new Usage(5, 0), 'test-model');
        $provider->expects($this->once())
            ->method('embed')
            ->willReturn($response);

        $store = new \LLMesh\Core\RAG\VectorStores\InMemoryVectorStore();

        $pipeline = Pipeline::make()
            ->load($loader)
            ->split($splitter)
            ->embed($provider)
            ->store($store);

        $result = $pipeline->run();

        $this->assertSame(1, $result->documentsLoaded);
        $this->assertSame(1, $result->chunksCreated);
        $this->assertSame(1, $result->chunksStored);
        $this->assertSame(1, $store->count());
    }
}
