<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Data;

use LLMesh\Core\Data\ChunkDelta;
use LLMesh\Core\Data\ToolCall;
use PHPUnit\Framework\TestCase;

final class ChunkDeltaTest extends TestCase
{
    public function testCanConstructChunkDeltaWithText(): void
    {
        $chunk = new ChunkDelta(text: 'Hello');

        $this->assertSame('Hello', $chunk->text);
        $this->assertNull($chunk->toolCall);
        $this->assertNull($chunk->finishReason);
    }

    public function testCanConstructChunkDeltaWithToolCall(): void
    {
        $toolCall = new ToolCall('call_123', 'search', ['query' => 'test']);
        $chunk = new ChunkDelta(toolCall: $toolCall);

        $this->assertNull($chunk->text);
        $this->assertSame($toolCall, $chunk->toolCall);
        $this->assertNull($chunk->finishReason);
    }

    public function testCanConstructChunkDeltaWithFinishReason(): void
    {
        $chunk = new ChunkDelta(finishReason: 'stop');

        $this->assertNull($chunk->text);
        $this->assertNull($chunk->toolCall);
        $this->assertSame('stop', $chunk->finishReason);
    }

    public function testCanConstructChunkDeltaWithAllProperties(): void
    {
        $toolCall = new ToolCall('call_456', 'tool', []);
        $chunk = new ChunkDelta(
            text: 'Some text',
            toolCall: $toolCall,
            finishReason: 'length',
        );

        $this->assertSame('Some text', $chunk->text);
        $this->assertSame($toolCall, $chunk->toolCall);
        $this->assertSame('length', $chunk->finishReason);
    }

    public function testCanConstructChunkDeltaWithNoProperties(): void
    {
        $chunk = new ChunkDelta();

        $this->assertNull($chunk->text);
        $this->assertNull($chunk->toolCall);
        $this->assertNull($chunk->finishReason);
    }

    public function testCanCreateTextChunk(): void
    {
        $chunk = ChunkDelta::text('Generated text');

        $this->assertSame('Generated text', $chunk->text);
        $this->assertNull($chunk->toolCall);
        $this->assertNull($chunk->finishReason);
    }

    public function testCanCreateToolCallChunk(): void
    {
        $toolCall = new ToolCall('id_1', 'action', ['param' => 'value']);
        $chunk = ChunkDelta::toolCall($toolCall);

        $this->assertNull($chunk->text);
        $this->assertSame($toolCall, $chunk->toolCall);
        $this->assertNull($chunk->finishReason);
    }

    public function testCanCreateFinishChunk(): void
    {
        $chunk = ChunkDelta::finish('tool_calls');

        $this->assertNull($chunk->text);
        $this->assertNull($chunk->toolCall);
        $this->assertSame('tool_calls', $chunk->finishReason);
    }

    public function testCanConvertTextChunkToArray(): void
    {
        $chunk = ChunkDelta::text('Response text');
        $array = $chunk->toArray();

        $this->assertIsArray($array);
        $this->assertSame('Response text', $array['text']);
        $this->assertNull($array['toolCall']);
        $this->assertNull($array['finishReason']);
    }

    public function testCanConvertToolCallChunkToArray(): void
    {
        $toolCall = new ToolCall('call_xyz', 'calculate', ['x' => 5, 'y' => 3]);
        $chunk = ChunkDelta::toolCall($toolCall);
        $array = $chunk->toArray();

        $this->assertIsArray($array);
        $this->assertNull($array['text']);
        $this->assertIsArray($array['toolCall']);
        $this->assertSame('call_xyz', $array['toolCall']['id']);
        $this->assertNull($array['finishReason']);
    }

    public function testCanConvertFinishChunkToArray(): void
    {
        $chunk = ChunkDelta::finish('max_tokens');
        $array = $chunk->toArray();

        $this->assertIsArray($array);
        $this->assertNull($array['text']);
        $this->assertNull($array['toolCall']);
        $this->assertSame('max_tokens', $array['finishReason']);
    }

    public function testChunkDeltaPropertiesAreReadonly(): void
    {
        $chunk = new ChunkDelta(text: 'test');

        // Trying to set a readonly property should fail
        $this->expectException(\Error::class);
        $chunk->text = 'modified';
    }
}
