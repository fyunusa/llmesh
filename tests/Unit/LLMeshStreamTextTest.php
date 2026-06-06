<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit;

use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Contracts\StreamInterface;
use LLMesh\Core\Data\ChunkDelta;
use LLMesh\Core\Events\StreamChunkReceived;
use LLMesh\Core\Events\StreamCompleted;
use LLMesh\Core\Events\StreamFailed;
use LLMesh\Core\Events\StreamStarted;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Core\Generators\StreamResponse;
use LLMesh\Core\LLMesh;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \LLMesh\Core\LLMesh
 */
final class LLMeshStreamTextTest extends TestCase
{
    protected function setUp(): void
    {
        LLMesh::withEventDispatcher(null);
    }

    // -------------------------------------------------------------------------
    // Basic contract
    // -------------------------------------------------------------------------

    public function testStreamTextReturnsStreamResponse(): void
    {
        $provider = $this->buildStreamingProvider([ChunkDelta::text('hi')]);
        $options  = GenerateTextOptions::make()->withPrompt('Hello');

        $result = LLMesh::streamText($provider, $options);

        self::assertInstanceOf(StreamResponse::class, $result);
    }

    public function testStreamTextIsLazyProviderNotCalledBeforeIteration(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        // stream() must be called exactly once — only when the generator is iterated
        $provider
            ->expects($this->once())
            ->method('stream')
            ->willReturn($this->makeStreamInterface([ChunkDelta::text('lazy')]));

        $options = GenerateTextOptions::make()->withPrompt('go');

        $stream = LLMesh::streamText($provider, $options);

        // Force consumption
        $stream->toText();
    }

    // -------------------------------------------------------------------------
    // Events
    // -------------------------------------------------------------------------

    public function testDispatchesStreamStartedBeforeFirstChunk(): void
    {
        $startedEvent = null;

        // createStub — we capture via willReturnCallback, no call count expectation
        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use (&$startedEvent): object {
                if ($event instanceof StreamStarted) {
                    $startedEvent = $event;
                }
                return $event;
            }
        );

        LLMesh::withEventDispatcher($dispatcher);

        $provider = $this->buildStreamingProvider([ChunkDelta::text('hello')]);
        $options  = GenerateTextOptions::make()->withPrompt('Hi');

        LLMesh::streamText($provider, $options)->toText();

        self::assertInstanceOf(StreamStarted::class, $startedEvent);
        self::assertSame($options, $startedEvent->options);
    }

    public function testDispatchesStreamChunkReceivedForEachChunk(): void
    {
        $chunkEvents = [];

        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use (&$chunkEvents): object {
                if ($event instanceof StreamChunkReceived) {
                    $chunkEvents[] = $event;
                }
                return $event;
            }
        );

        LLMesh::withEventDispatcher($dispatcher);

        $provider = $this->buildStreamingProvider([
            ChunkDelta::text('a'),
            ChunkDelta::text('b'),
            ChunkDelta::text('c'),
        ]);

        LLMesh::streamText($provider, GenerateTextOptions::make()->withPrompt('go'))->toText();

        self::assertCount(3, $chunkEvents);
        self::assertSame(0, $chunkEvents[0]->chunkIndex);
        self::assertSame(1, $chunkEvents[1]->chunkIndex);
        self::assertSame(2, $chunkEvents[2]->chunkIndex);
        self::assertSame('a', $chunkEvents[0]->chunk->text);
    }

    public function testDispatchesStreamCompletedAfterAllChunks(): void
    {
        $completedEvent = null;

        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use (&$completedEvent): object {
                if ($event instanceof StreamCompleted) {
                    $completedEvent = $event;
                }
                return $event;
            }
        );

        LLMesh::withEventDispatcher($dispatcher);

        $provider = $this->buildStreamingProvider([
            ChunkDelta::text('x'),
            ChunkDelta::text('y'),
        ]);

        LLMesh::streamText($provider, GenerateTextOptions::make()->withPrompt('go'))->toText();

        self::assertInstanceOf(StreamCompleted::class, $completedEvent);
        self::assertSame(2, $completedEvent->totalChunks);
        self::assertGreaterThanOrEqual(0, $completedEvent->durationMs);
    }

    public function testDispatchesStreamFailedOnProviderException(): void
    {
        $failedEvent = null;

        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use (&$failedEvent): object {
                if ($event instanceof StreamFailed) {
                    $failedEvent = $event;
                }
                return $event;
            }
        );

        LLMesh::withEventDispatcher($dispatcher);

        $exception = new \RuntimeException('Provider exploded');
        $provider  = $this->createStub(ProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('stream')->willReturn(
            $this->makeStreamInterface([], $exception)
        );

        $stream = LLMesh::streamText($provider, GenerateTextOptions::make()->withPrompt('hi'));

        try {
            $stream->toText();
            self::fail('Expected RuntimeException to propagate');
        } catch (\RuntimeException) {
            // expected
        }

        self::assertInstanceOf(StreamFailed::class, $failedEvent);
        self::assertSame($exception, $failedEvent->exception);
    }

    public function testStreamCompletedIsNotDispatchedWhenStreamFails(): void
    {
        $completedEvent = null;

        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use (&$completedEvent): object {
                if ($event instanceof StreamCompleted) {
                    $completedEvent = $event;
                }
                return $event;
            }
        );

        LLMesh::withEventDispatcher($dispatcher);

        $provider = $this->createStub(ProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('stream')->willReturn(
            $this->makeStreamInterface([], new \RuntimeException('boom'))
        );

        try {
            LLMesh::streamText(
                $provider,
                GenerateTextOptions::make()->withPrompt('hi')
            )->toText();
        } catch (\RuntimeException) {
            // expected
        }

        self::assertNull($completedEvent, 'StreamCompleted must not fire when the stream fails');
    }

    // -------------------------------------------------------------------------
    // Non-streaming provider
    // -------------------------------------------------------------------------

    public function testStreamTextThrowsRuntimeExceptionForNonStreamingProvider(): void
    {
        $provider = $this->createStub(ProviderInterface::class);
        $provider->method('supports')->willReturn(false);

        $this->expectException(\RuntimeException::class);

        LLMesh::streamText(
            $provider,
            GenerateTextOptions::make()->withPrompt('hi')
        )->toText(); // consumption triggers the generator
    }

    // -------------------------------------------------------------------------
    // Works without event dispatcher
    // -------------------------------------------------------------------------

    public function testWorksWithoutEventDispatcher(): void
    {
        LLMesh::withEventDispatcher(null);

        $provider = $this->buildStreamingProvider([
            ChunkDelta::text('no'),
            ChunkDelta::text(' dispatcher'),
        ]);

        $result = LLMesh::streamText(
            $provider,
            GenerateTextOptions::make()->withPrompt('hi'),
        )->toText();

        self::assertSame('no dispatcher', $result);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a provider stub that returns streaming = true and yields the
     * given chunks from provider->stream().
     *
     * @param ChunkDelta[] $chunks
     */
    private function buildStreamingProvider(array $chunks): ProviderInterface
    {
        $provider = $this->createStub(ProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('stream')->willReturn($this->makeStreamInterface($chunks));
        return $provider;
    }

    /**
     * Create a StreamInterface stub whose getChunks() yields the given array,
     * then optionally throws.
     *
     * @param ChunkDelta[]    $chunks
     * @param \Throwable|null $throws Exception to throw after yielding all chunks
     */
    private function makeStreamInterface(array $chunks, ?\Throwable $throws = null): StreamInterface
    {
        $stub = $this->createStub(StreamInterface::class);
        $stub->method('getChunks')->willReturn(
            (static function () use ($chunks, $throws): \Generator {
                yield from $chunks;
                if ($throws !== null) {
                    throw $throws;
                }
            })()
        );
        return $stub;
    }
}
