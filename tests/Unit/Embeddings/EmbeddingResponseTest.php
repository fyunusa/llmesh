<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Embeddings;

use LLMesh\Core\Embeddings\EmbeddingResponse;
use LLMesh\Core\Generators\Usage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\Embeddings\EmbeddingResponse
 */
final class EmbeddingResponseTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeResponse(array $embedding, string $model = 'test-model'): EmbeddingResponse
    {
        return new EmbeddingResponse(
            embedding:  $embedding,
            dimensions: count($embedding),
            usage:      new Usage(5, 0),
            model:      $model,
        );
    }

    // -------------------------------------------------------------------------
    // cosineSimilarity — known vectors
    // -------------------------------------------------------------------------

    public function testCosineSimilarityOfIdenticalVectorsIsOne(): void
    {
        $a = $this->makeResponse([1.0, 0.0, 0.0]);
        $this->assertEqualsWithDelta(1.0, $a->cosineSimilarity($a), 1e-9);
    }

    public function testCosineSimilarityOfOrthogonalVectorsIsZero(): void
    {
        $a = $this->makeResponse([1.0, 0.0]);
        $b = $this->makeResponse([0.0, 1.0]);
        $this->assertEqualsWithDelta(0.0, $a->cosineSimilarity($b), 1e-9);
    }

    public function testCosineSimilarityOfOppositeVectorsIsMinusOne(): void
    {
        $a = $this->makeResponse([1.0, 0.0]);
        $b = $this->makeResponse([-1.0, 0.0]);
        $this->assertEqualsWithDelta(-1.0, $a->cosineSimilarity($b), 1e-9);
    }

    public function testCosineSimilarityOfArbitraryVectors(): void
    {
        // [1,1] vs [1,0]: cos(45°) = 1/√2 ≈ 0.7071
        $a = $this->makeResponse([1.0, 1.0]);
        $b = $this->makeResponse([1.0, 0.0]);
        $this->assertEqualsWithDelta(1.0 / sqrt(2.0), $a->cosineSimilarity($b), 1e-6);
    }

    public function testCosineSimilarityReturnsZeroForZeroVector(): void
    {
        $a = $this->makeResponse([0.0, 0.0, 0.0]);
        $b = $this->makeResponse([1.0, 2.0, 3.0]);
        // Division by zero is guarded — must return 0.0
        $this->assertEqualsWithDelta(0.0, $a->cosineSimilarity($b), 1e-9);
        $this->assertEqualsWithDelta(0.0, $b->cosineSimilarity($a), 1e-9);
    }

    public function testCosineSimilarityBothZeroVectorsReturnsZero(): void
    {
        $a = $this->makeResponse([0.0, 0.0]);
        $b = $this->makeResponse([0.0, 0.0]);
        $this->assertEqualsWithDelta(0.0, $a->cosineSimilarity($b), 1e-9);
    }

    // -------------------------------------------------------------------------
    // fromArray()
    // -------------------------------------------------------------------------

    public function testFromArrayBuildsCorrectResponse(): void
    {
        $raw = [
            'data'  => [['embedding' => [0.1, 0.2, 0.3], 'index' => 0]],
            'model' => 'text-embedding-3-small',
            'usage' => ['prompt_tokens' => 7],
        ];

        $response = EmbeddingResponse::fromArray($raw, function (array $data): array {
            return [
                'embedding' => $data['data'][0]['embedding'],
                'model'     => $data['model'],
                'usage'     => ['input_tokens' => $data['usage']['prompt_tokens']],
            ];
        });

        $this->assertSame([0.1, 0.2, 0.3], $response->getEmbedding());
        $this->assertSame(3, $response->getDimensions());
        $this->assertSame('text-embedding-3-small', $response->model);
        $this->assertSame(7, $response->getUsage()->getInputTokens());
    }

    // -------------------------------------------------------------------------
    // toArray() returns float[]
    // -------------------------------------------------------------------------

    public function testToArrayReturnsEmbeddingVector(): void
    {
        $vector   = [0.1, 0.2, 0.3];
        $response = $this->makeResponse($vector);

        $this->assertSame($vector, $response->toArray());
    }

    // -------------------------------------------------------------------------
    // toFullArray() returns full metadata
    // -------------------------------------------------------------------------

    public function testToFullArrayContainsAllFields(): void
    {
        $response = $this->makeResponse([1.0, 2.0]);
        $full     = $response->toFullArray();

        $this->assertArrayHasKey('embedding', $full);
        $this->assertArrayHasKey('dimensions', $full);
        $this->assertArrayHasKey('usage', $full);
        $this->assertArrayHasKey('model', $full);
        $this->assertSame(2, $full['dimensions']);
    }

    // -------------------------------------------------------------------------
    // Basic accessors
    // -------------------------------------------------------------------------

    public function testGetDimensionsMatchesVectorLength(): void
    {
        $response = $this->makeResponse([0.1, 0.2, 0.3, 0.4]);
        $this->assertSame(4, $response->getDimensions());
    }

    public function testGetEmbeddingReturnsVector(): void
    {
        $vector   = [0.5, 0.6];
        $response = $this->makeResponse($vector);
        $this->assertSame($vector, $response->getEmbedding());
    }
}
