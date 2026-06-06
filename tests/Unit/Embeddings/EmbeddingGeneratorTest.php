<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Embeddings;

use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Embeddings\EmbeddingGenerator;
use LLMesh\Core\Embeddings\EmbeddingResponse;
use LLMesh\Core\Generators\Usage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\Embeddings\EmbeddingGenerator
 */
final class EmbeddingGeneratorTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeEmbeddingResponse(array $vector): EmbeddingResponse
    {
        return new EmbeddingResponse(
            embedding:  $vector,
            dimensions: count($vector),
            usage:      new Usage(5, 0),
            model:      'test-model',
        );
    }

    // -------------------------------------------------------------------------
    // embed()
    // -------------------------------------------------------------------------

    public function testEmbedReturnsSingleEmbeddingResponse(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->with('embeddings')->willReturn(true);
        $provider->expects($this->once())
            ->method('embed')
            ->with('hello', [])
            ->willReturn($this->makeEmbeddingResponse([0.1, 0.2, 0.3]));

        $generator = new EmbeddingGenerator();
        $result    = $generator->embed($provider, 'hello');

        $this->assertInstanceOf(EmbeddingResponse::class, $result);
        $this->assertSame([0.1, 0.2, 0.3], $result->getEmbedding());
    }

    public function testEmbedPassesOptionsToProvider(): void
    {
        $options  = ['model' => 'text-embedding-3-large'];
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->with('embeddings')->willReturn(true);
        $provider->expects($this->once())
            ->method('embed')
            ->with('text', $options)
            ->willReturn($this->makeEmbeddingResponse([1.0]));

        (new EmbeddingGenerator())->embed($provider, 'text', $options);
    }

    // -------------------------------------------------------------------------
    // embedBatch() — correct count
    // -------------------------------------------------------------------------

    public function testEmbedBatchReturnsCorrectNumberOfResponses(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->willReturnCallback(function ($cap) {
            return $cap === 'embeddings';
        });
        $provider->method('embed')
            ->willReturn($this->makeEmbeddingResponse([0.1, 0.2]));

        $generator = new EmbeddingGenerator();
        $results   = $generator->embedBatch($provider, ['a', 'b', 'c']);

        $this->assertCount(3, $results);
    }

    // -------------------------------------------------------------------------
    // embedBatch() — index ordering preserved
    // -------------------------------------------------------------------------

    public function testEmbedBatchPreservesInputIndexOrder(): void
    {
        $callIndex = 0;
        $provider  = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->willReturnCallback(function ($cap) {
            return $cap === 'embeddings';
        });
        $provider->method('embed')
            ->willReturnCallback(function (string $input) use (&$callIndex): EmbeddingResponse {
                $callIndex++;
                return $this->makeEmbeddingResponse([(float) $callIndex]);
            });

        $results = (new EmbeddingGenerator())->embedBatch($provider, ['first', 'second', 'third']);

        // Results must be in the same order as inputs
        $this->assertSame([1.0], $results[0]->getEmbedding());
        $this->assertSame([2.0], $results[1]->getEmbedding());
        $this->assertSame([3.0], $results[2]->getEmbedding());
    }

    // -------------------------------------------------------------------------
    // embedBatch() — provider WITHOUT 'embeddings' support → sequential calls
    // -------------------------------------------------------------------------

    public function testEmbedBatchMakesIndividualCallsWhenProviderLacksBatchSupport(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->willReturnCallback(function ($cap) {
            return $cap === 'embeddings';
        });

        // Must be called exactly once per input
        $provider->expects($this->exactly(3))
            ->method('embed')
            ->willReturn($this->makeEmbeddingResponse([0.0]));

        (new EmbeddingGenerator())->embedBatch($provider, ['a', 'b', 'c']);
    }

    // -------------------------------------------------------------------------
    // embedBatch() — provider WITH 'embeddings' support but NO 'batch_embeddings'
    // -------------------------------------------------------------------------

    public function testEmbedBatchMakesIndividualCallsEvenWhenProviderSupportsEmbeddingsButNotBatch(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->willReturnCallback(function ($cap) {
            return $cap === 'embeddings';
        });

        $provider->expects($this->exactly(2))
            ->method('embed')
            ->willReturn($this->makeEmbeddingResponse([0.5]));

        $results = (new EmbeddingGenerator())->embedBatch($provider, ['x', 'y']);
        $this->assertCount(2, $results);
    }

    // -------------------------------------------------------------------------
    // embedBatch() — empty inputs
    // -------------------------------------------------------------------------

    public function testEmbedBatchWithEmptyInputsReturnsEmptyArray(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->with('embeddings')->willReturn(true);
        $provider->expects($this->never())->method('embed');

        $results = (new EmbeddingGenerator())->embedBatch($provider, []);
        $this->assertSame([], $results);
    }

    // -------------------------------------------------------------------------
    // embed() — wraps non-EmbeddingResponse implementations
    // -------------------------------------------------------------------------

    public function testEmbedWrapsNonConcreteEmbeddingResponseInterface(): void
    {
        $mockResponse = $this->createMock(\LLMesh\Core\Contracts\EmbeddingResponseInterface::class);
        $mockResponse->method('getEmbedding')->willReturn([0.9, 0.1]);
        $mockResponse->method('getDimensions')->willReturn(2);
        $mockResponse->method('getUsage')->willReturn(new Usage(3, 0));

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->with('embeddings')->willReturn(true);
        $provider->method('embed')->willReturn($mockResponse);

        $result = (new EmbeddingGenerator())->embed($provider, 'text');

        $this->assertInstanceOf(EmbeddingResponse::class, $result);
        $this->assertSame([0.9, 0.1], $result->getEmbedding());
    }

    // -------------------------------------------------------------------------
    // New Tests
    // -------------------------------------------------------------------------

    public function testEmbedThrowsRuntimeExceptionForNonEmbeddingProvider(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('supports')
            ->with('embeddings')
            ->willReturn(false);
        $provider->expects($this->never())->method('embed');

        $this->expectException(\RuntimeException::class);

        $generator = new EmbeddingGenerator();
        $generator->embed($provider, 'test input');
    }

    public function testEmbedBatchThrowsRuntimeExceptionForNonEmbeddingProvider(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('supports')
            ->with('embeddings')
            ->willReturn(false);
        $provider->expects($this->never())->method('embed');
        $provider->expects($this->never())->method('embedBatch');

        $this->expectException(\RuntimeException::class);

        $generator = new EmbeddingGenerator();
        $generator->embedBatch($provider, ['input one', 'input two']);
    }

    public function testEmbedBatchUsesSingleApiCallForBatchCapableProvider(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->willReturnCallback(function ($cap) {
            return $cap === 'embeddings' || $cap === 'batch_embeddings';
        });

        $mockResponses = [
            $this->makeEmbeddingResponse([0.1, 0.2]),
            $this->makeEmbeddingResponse([0.3, 0.4]),
            $this->makeEmbeddingResponse([0.5, 0.6]),
        ];

        // embedBatch called ONCE (not 3 times)
        $provider->expects($this->once())
            ->method('embedBatch')
            ->with(['input 1', 'input 2', 'input 3'], [])
            ->willReturn($mockResponses);

        $provider->expects($this->never())->method('embed');

        $generator = new EmbeddingGenerator();
        $results   = $generator->embedBatch($provider, ['input 1', 'input 2', 'input 3']);

        $this->assertCount(3, $results);
        $this->assertSame([0.1, 0.2], $results[0]->getEmbedding());
        $this->assertSame([0.3, 0.4], $results[1]->getEmbedding());
        $this->assertSame([0.5, 0.6], $results[2]->getEmbedding());
    }
}
