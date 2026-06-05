<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Generators;

use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Contracts\StreamInterface;
use LLMesh\Core\Data\ChunkDelta;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Core\Generators\StreamGenerator;
use LLMesh\Core\Generators\StreamResponse;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\Generators\StreamGenerator
 */
final class StreamGeneratorTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Provider capability guard
    // -------------------------------------------------------------------------

    public function testThrowsRuntimeExceptionWhenProviderDoesNotSupportStreaming(): void
    {
        $provider = $this->createStub(ProviderInterface::class);
        $provider->method('supports')->willReturn(false);

        $generator = new StreamGenerator($provider);
        $options   = GenerateTextOptions::make()->withPrompt('Hello');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not support streaming');

        $generator->stream($options);
    }

    public function testThrowsRuntimeExceptionMessageContainsProviderClass(): void
    {
        $provider = $this->createStub(ProviderInterface::class);
        $provider->method('supports')->willReturn(false);

        $generator = new StreamGenerator($provider);
        $options   = GenerateTextOptions::make()->withPrompt('Hello');

        try {
            $generator->stream($options);
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            // The message must identify the provider class so callers know what to fix
            self::assertStringContainsString(get_class($provider), $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Delegation to provider
    // -------------------------------------------------------------------------

    public function testReturnsStreamResponseWrappingProviderChunks(): void
    {
        [$provider, $stream] = $this->buildMockProvider([
            ChunkDelta::text('Hello'),
            ChunkDelta::text(', '),
            ChunkDelta::text('world!'),
        ]);

        $generator = new StreamGenerator($provider);
        $options   = GenerateTextOptions::make()->withPrompt('Hi');

        $response = $generator->stream($options);

        self::assertInstanceOf(StreamResponse::class, $response);
        self::assertSame('Hello, world!', $response->toText());
    }

    public function testMockProviderYieldsThreeChunksToText(): void
    {
        [$provider] = $this->buildMockProvider([
            ChunkDelta::text('one'),
            ChunkDelta::text('two'),
            ChunkDelta::text('three'),
        ]);

        $generator = new StreamGenerator($provider);
        $stream    = $generator->stream(GenerateTextOptions::make()->withPrompt('go'));

        self::assertSame('onetwothree', $stream->toText());
    }

    public function testPassesPromptAsUserMessageToProvider(): void
    {
        $mockStream = $this->makeStreamInterface([ChunkDelta::text('ok')]);

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider
            ->expects($this->once())
            ->method('stream')
            ->with(
                $this->callback(function (array $messages): bool {
                    self::assertCount(1, $messages);
                    self::assertSame('user', $messages[0]->role->value);
                    self::assertSame('test prompt', $messages[0]->content);
                    return true;
                }),
                $this->isArray(),
            )
            ->willReturn($mockStream);

        $generator = new StreamGenerator($provider);
        $generator->stream(GenerateTextOptions::make()->withPrompt('test prompt'))->toText();
    }

    public function testPassesProviderOptionsCorrectly(): void
    {
        $mockStream = $this->makeStreamInterface([ChunkDelta::text('ok')]);

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider
            ->expects($this->once())
            ->method('stream')
            ->with(
                $this->isArray(),
                $this->callback(function (array $opts): bool {
                    self::assertSame(0.5, $opts['temperature']);
                    self::assertSame(256, $opts['max_tokens']);
                    return true;
                }),
            )
            ->willReturn($mockStream);

        $options = GenerateTextOptions::make()
            ->withPrompt('hi')
            ->withTemperature(0.5)
            ->withMaxTokens(256);

        (new StreamGenerator($provider))->stream($options)->toText();
    }

    public function testValidatesOptionsBeforeCallingProvider(): void
    {
        $provider = $this->createStub(ProviderInterface::class);
        $provider->method('supports')->willReturn(true);

        $generator = new StreamGenerator($provider);

        // Empty options (no prompt, no messages) must fail validation
        $this->expectException(\LLMesh\Core\Exceptions\ValidationException::class);

        $generator->stream(GenerateTextOptions::make());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a (provider stub, StreamInterface stub) pair that yields the given chunks.
     *
     * @param ChunkDelta[] $chunks
     * @return array{0: ProviderInterface, 1: StreamInterface}
     */
    private function buildMockProvider(array $chunks): array
    {
        $stream   = $this->makeStreamInterface($chunks);
        $provider = $this->createStub(ProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('stream')->willReturn($stream);

        return [$provider, $stream];
    }

    /**
     * Create a StreamInterface stub whose getChunks() yields the given array.
     *
     * @param ChunkDelta[] $chunks
     */
    private function makeStreamInterface(array $chunks): StreamInterface
    {
        $stub = $this->createStub(StreamInterface::class);
        $stub->method('getChunks')->willReturn(
            (static function () use ($chunks): \Generator {
                yield from $chunks;
            })()
        );

        return $stub;
    }
}
