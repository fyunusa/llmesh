<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Generators;

use LLMesh\Core\Data\ChunkDelta;
use LLMesh\Core\Generators\StreamResponse;
use LLMesh\Core\Generators\Usage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\Generators\StreamResponse
 */
final class StreamResponseTest extends TestCase
{
    // -------------------------------------------------------------------------
    // toText()
    // -------------------------------------------------------------------------

    public function testToTextConcatenatesAllTextDeltas(): void
    {
        $stream = $this->makeStream([
            ChunkDelta::text('Hello'),
            ChunkDelta::text(', '),
            ChunkDelta::text('world!'),
        ]);

        self::assertSame('Hello, world!', $stream->toText());
    }

    public function testToTextSkipsNullTextChunks(): void
    {
        $stream = $this->makeStream([
            ChunkDelta::text('Part one'),
            ChunkDelta::finish('end_turn'),   // text === null
            ChunkDelta::text(' part two'),
        ]);

        self::assertSame('Part one part two', $stream->toText());
    }

    public function testToTextSkipsToolCallChunks(): void
    {
        $stream = $this->makeStream([
            ChunkDelta::text('prefix'),
            ChunkDelta::toolCall(new \LLMesh\Core\Data\ToolCall('id1', 'fn', [])),
            ChunkDelta::text('suffix'),
        ]);

        self::assertSame('prefixsuffix', $stream->toText());
    }

    public function testToTextOnEmptyStreamReturnsEmptyString(): void
    {
        $stream = $this->makeStream([]);

        self::assertSame('', $stream->toText());
    }

    public function testToTextDoesNotSkipEmptyStringChunks(): void
    {
        // An empty-string text chunk is still text — should not be skipped.
        $chunks = [
            new ChunkDelta(text: ''),
            ChunkDelta::text('X'),
        ];

        $stream = $this->makeStream($chunks);

        self::assertSame('X', $stream->toText());
    }

    // -------------------------------------------------------------------------
    // getUsage()
    // -------------------------------------------------------------------------

    public function testGetUsageThrowsLogicExceptionBeforeStreamIsExhausted(): void
    {
        $stream = $this->makeStream([
            ChunkDelta::text('first'),
            ChunkDelta::text('second'),
        ]);

        // Do NOT consume the stream — getUsage() must throw immediately.
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot get usage before stream is exhausted');

        $stream->getUsage();
    }

    public function testGetUsageReturnsDefaultAfterExhaustion(): void
    {
        $stream = $this->makeStream([ChunkDelta::text('chunk')]);
        $stream->toText(); // exhaust the stream

        $usage = $stream->getUsage();

        self::assertSame(0, $usage->getInputTokens());
        self::assertSame(0, $usage->getOutputTokens());
    }

    // -------------------------------------------------------------------------
    // pipe()
    // -------------------------------------------------------------------------

    public function testPipeCallsCallbackForEachChunk(): void
    {
        $chunks = [
            ChunkDelta::text('a'),
            ChunkDelta::text('b'),
            ChunkDelta::text('c'),
        ];
        $stream  = $this->makeStream($chunks);
        $received = [];

        $stream->pipe(function (ChunkDelta $chunk) use (&$received): void {
            $received[] = $chunk->text;
        });

        self::assertSame(['a', 'b', 'c'], $received);
    }

    // -------------------------------------------------------------------------
    // Iterator interface
    // -------------------------------------------------------------------------

    public function testCanIterateWithForeach(): void
    {
        $chunks = [ChunkDelta::text('X'), ChunkDelta::text('Y')];
        $stream = $this->makeStream($chunks);

        $texts = [];
        foreach ($stream as $chunk) {
            $texts[] = $chunk->text;
        }

        self::assertSame(['X', 'Y'], $texts);
    }

    public function testValidReturnsFalseAfterExhaustion(): void
    {
        $stream = $this->makeStream([ChunkDelta::text('only')]);

        // Advance through all items via the Iterator interface
        $stream->rewind();
        while ($stream->valid()) {
            $stream->next();
        }

        self::assertFalse($stream->valid());
    }

    // -------------------------------------------------------------------------
    // toSSE() — uses output buffering to capture echo'd content
    // -------------------------------------------------------------------------

    public function testToSSEOutputsDataPrefixForEachChunk(): void
    {
        $chunks = [
            ChunkDelta::text('Hello'),
            ChunkDelta::text(' world'),
        ];
        $stream = $this->makeStream($chunks);

        ob_start();
        $stream->toSSE();
        $output = ob_get_clean();

        $lines = array_values(array_filter(explode("\n", $output)));

        // Expect: data: <json>, data: <json>, data: [DONE]
        self::assertCount(3, $lines, 'Expected 2 data lines + [DONE]');
        self::assertStringStartsWith('data: ', $lines[0]);
        self::assertStringStartsWith('data: ', $lines[1]);
        self::assertSame('data: [DONE]', $lines[2]);
    }

    public function testToSSEEndsWithDoneSentinel(): void
    {
        $stream = $this->makeStream([ChunkDelta::text('chunk')]);

        ob_start();
        $stream->toSSE();
        $output = ob_get_clean();

        self::assertStringEndsWith("data: [DONE]\n\n", $output);
    }

    public function testToSSEOutputsValidJsonPerChunk(): void
    {
        $stream = $this->makeStream([ChunkDelta::text('abc')]);

        ob_start();
        $stream->toSSE();
        $output = ob_get_clean();

        // Split on double newline (SSE event separator)
        $events = array_filter(explode("\n\n", $output));
        $events = array_values($events);

        // First event is the chunk
        $firstLine = $events[0];
        self::assertStringStartsWith('data: ', $firstLine);
        $json = json_decode(substr($firstLine, 6), true);
        self::assertIsArray($json);
        self::assertSame('abc', $json['text']);
    }

    public function testToSSEEmptyStreamOnlyOutputsDone(): void
    {
        $stream = $this->makeStream([]);

        ob_start();
        $stream->toSSE();
        $output = ob_get_clean();

        self::assertSame("data: [DONE]\n\n", $output);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a StreamResponse from a plain array of ChunkDelta objects.
     *
     * @param ChunkDelta[] $chunks
     */
    private function makeStream(array $chunks): StreamResponse
    {
        $generator = (static function () use ($chunks): \Generator {
            yield from $chunks;
        })();

        return new StreamResponse($generator);
    }
}
