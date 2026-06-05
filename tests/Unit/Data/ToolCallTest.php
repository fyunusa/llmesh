<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Data;

use LLMesh\Core\Data\ToolCall;
use PHPUnit\Framework\TestCase;

final class ToolCallTest extends TestCase
{
    public function testCanConstructToolCall(): void
    {
        $arguments = ['city' => 'San Francisco', 'unit' => 'celsius'];
        $toolCall = new ToolCall(
            id: 'call_123',
            name: 'get_weather',
            arguments: $arguments,
        );

        $this->assertSame('call_123', $toolCall->id);
        $this->assertSame('get_weather', $toolCall->name);
        $this->assertSame($arguments, $toolCall->arguments);
    }

    public function testCanConstructToolCallWithEmptyArguments(): void
    {
        $toolCall = new ToolCall(
            id: 'call_456',
            name: 'get_current_time',
            arguments: [],
        );

        $this->assertSame('call_456', $toolCall->id);
        $this->assertSame('get_current_time', $toolCall->name);
        $this->assertSame([], $toolCall->arguments);
    }

    public function testCanConvertToolCallToArray(): void
    {
        $arguments = ['query' => 'PHP 8.1'];
        $toolCall = new ToolCall(
            id: 'call_789',
            name: 'search',
            arguments: $arguments,
        );

        $array = $toolCall->toArray();

        $this->assertIsArray($array);
        $this->assertSame('call_789', $array['id']);
        $this->assertSame('search', $array['name']);
        $this->assertSame($arguments, $array['arguments']);
    }

    public function testToolCallPropertiesAreReadonly(): void
    {
        $toolCall = new ToolCall('call_1', 'tool', []);

        // Trying to set a readonly property should fail
        $this->expectException(\Error::class);
        $toolCall->name = 'modified';
    }

    public function testCanConvertComplexArgumentsToArray(): void
    {
        $arguments = [
            'name' => 'John',
            'age' => 30,
            'tags' => ['developer', 'php'],
            'meta' => ['active' => true],
        ];

        $toolCall = new ToolCall('call_complex', 'process', $arguments);
        $array = $toolCall->toArray();

        $this->assertSame($arguments, $array['arguments']);
    }
}
