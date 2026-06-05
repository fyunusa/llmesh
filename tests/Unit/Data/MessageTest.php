<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Data;

use LLMesh\Core\Data\Message;
use LLMesh\Core\Data\MessageRole;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    public function testCanConstructMessageWithAllProperties(): void
    {
        $message = new Message(
            role: MessageRole::USER,
            content: 'Hello, world!',
            toolCallId: 'call_123',
            toolName: 'search',
        );

        $this->assertSame(MessageRole::USER, $message->role);
        $this->assertSame('Hello, world!', $message->content);
        $this->assertSame('call_123', $message->toolCallId);
        $this->assertSame('search', $message->toolName);
    }

    public function testCanConstructMessageWithMinimalProperties(): void
    {
        $message = new Message(
            role: MessageRole::ASSISTANT,
            content: 'Response text',
        );

        $this->assertSame(MessageRole::ASSISTANT, $message->role);
        $this->assertSame('Response text', $message->content);
        $this->assertNull($message->toolCallId);
        $this->assertNull($message->toolName);
    }

    public function testCanCreateUserMessage(): void
    {
        $message = Message::user('What is the weather?');

        $this->assertSame(MessageRole::USER, $message->role);
        $this->assertSame('What is the weather?', $message->content);
        $this->assertNull($message->toolCallId);
        $this->assertNull($message->toolName);
    }

    public function testCanCreateAssistantMessage(): void
    {
        $message = Message::assistant('The weather is sunny.');

        $this->assertSame(MessageRole::ASSISTANT, $message->role);
        $this->assertSame('The weather is sunny.', $message->content);
    }

    public function testCanCreateSystemMessage(): void
    {
        $message = Message::system('You are a helpful assistant.');

        $this->assertSame(MessageRole::SYSTEM, $message->role);
        $this->assertSame('You are a helpful assistant.', $message->content);
    }

    public function testCanCreateToolMessage(): void
    {
        $message = Message::tool('Weather data: sunny, 72°F', 'call_123', 'get_weather');

        $this->assertSame(MessageRole::TOOL, $message->role);
        $this->assertSame('Weather data: sunny, 72°F', $message->content);
        $this->assertSame('call_123', $message->toolCallId);
        $this->assertSame('get_weather', $message->toolName);
    }

    public function testCanConvertMessageToArray(): void
    {
        $message = Message::user('Test message');
        $array = $message->toArray();

        $this->assertIsArray($array);
        $this->assertSame('user', $array['role']);
        $this->assertSame('Test message', $array['content']);
        $this->assertNull($array['toolCallId']);
        $this->assertNull($array['toolName']);
    }

    public function testMessagePropertiesAreReadonly(): void
    {
        $message = Message::user('Test');

        // Trying to set a readonly property should fail
        $this->expectException(\Error::class);
        $message->content = 'Modified';
    }
}
