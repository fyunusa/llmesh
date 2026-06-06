<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\RAG;

use LLMesh\Core\RAG\VectorStores\InMemoryVectorStore;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\RAG\VectorStores\InMemoryVectorStore
 */
final class InMemoryVectorStoreTest extends TestCase
{
    private InMemoryVectorStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryVectorStore();
    }

    // -------------------------------------------------------------------------
    // upsert + query
    // -------------------------------------------------------------------------

    public function testUpsertAndQueryReturnsMostSimilarVectors(): void
    {
        // Three unit vectors: A points right, B points slightly right-up, C points up
        $this->store->upsert('A', [1.0, 0.0], ['label' => 'A']);
        $this->store->upsert('B', [0.8, 0.2], ['label' => 'B']);
        $this->store->upsert('C', [0.0, 1.0], ['label' => 'C']);

        // Query near A
        $results = $this->store->query([1.0, 0.0], topK: 2);

        $this->assertCount(2, $results);
        $this->assertSame('A', $results[0]['id']); // most similar to [1,0]
        $this->assertSame('B', $results[1]['id']);
    }

    public function testQueryReturnsTopKResults(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->store->upsert("vec-$i", [(float) $i, 0.0], []);
        }

        $results = $this->store->query([9.0, 0.0], topK: 3);

        $this->assertCount(3, $results);
    }

    public function testQueryResultsAreOrderedBySimilarityDescending(): void
    {
        $this->store->upsert('close',  [1.0, 0.01], []);
        $this->store->upsert('medium', [0.8, 0.6],  []);
        $this->store->upsert('far',    [0.0, 1.0],  []);

        $results = $this->store->query([1.0, 0.0], topK: 3);

        $this->assertSame('close',  $results[0]['id']);
        $this->assertSame('medium', $results[1]['id']);
        $this->assertSame('far',    $results[2]['id']);
    }

    public function testQueryOnEmptyStoreReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->store->query([1.0, 0.0], topK: 5));
    }

    // -------------------------------------------------------------------------
    // upsert (idempotency)
    // -------------------------------------------------------------------------

    public function testUpsertOverwritesExistingVector(): void
    {
        $this->store->upsert('doc-1', [0.0, 1.0], ['version' => '1']);
        $this->store->upsert('doc-1', [1.0, 0.0], ['version' => '2']);

        $this->assertSame(1, $this->store->count());

        $results = $this->store->query([1.0, 0.0], topK: 1);
        $this->assertSame('2', $results[0]['metadata']['version']);
    }

    // -------------------------------------------------------------------------
    // delete
    // -------------------------------------------------------------------------

    public function testDeleteRemovesVector(): void
    {
        $this->store->upsert('keep', [1.0, 0.0], []);
        $this->store->upsert('del',  [1.0, 0.0], []);

        $this->store->delete('del');

        $this->assertTrue($this->store->has('keep'));
        $this->assertFalse($this->store->has('del'));
        $this->assertSame(1, $this->store->count());
    }

    public function testDeleteNonExistentIdIsNoop(): void
    {
        $this->store->upsert('keep', [1.0, 0.0], []);
        $this->store->delete('ghost');

        $this->assertSame(1, $this->store->count());
    }

    // -------------------------------------------------------------------------
    // count / has
    // -------------------------------------------------------------------------

    public function testCountReflectsStoredVectors(): void
    {
        $this->assertSame(0, $this->store->count());

        $this->store->upsert('a', [1.0], []);
        $this->store->upsert('b', [2.0], []);

        $this->assertSame(2, $this->store->count());
    }

    public function testScoreIsReturnedInResults(): void
    {
        $this->store->upsert('a', [1.0, 0.0], ['x' => 'y']);

        $results = $this->store->query([1.0, 0.0], topK: 1);

        $this->assertArrayHasKey('score',    $results[0]);
        $this->assertArrayHasKey('id',       $results[0]);
        $this->assertArrayHasKey('metadata', $results[0]);
        $this->assertEqualsWithDelta(1.0, $results[0]['score'], 1e-6);
    }
}
