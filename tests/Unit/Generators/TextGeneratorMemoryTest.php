<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Generators;

use LLMesh\Core\Contracts\MemoryStoreInterface;
use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Core\Generators\TextGenerator;
use LLMesh\Core\Generators\TextResponse;
use LLMesh\Core\Generators\Usage;
use PHPUnit\Framework\TestCase;

final class TextGeneratorMemoryTest extends TestCase
{
    public function testLoadsHistoryFromMemoryAndAppends(): void
    {
        $sessionId = 'test-session';
        $appendedMessages = [];

        $mockMemory = $this->createMock(MemoryStoreInterface::class);
        $mockMemory
            ->expects($this->once())
            ->method('get')
            ->with($sessionId)
            ->willReturn([
                ['role' => 'user', 'content' => 'Previous message', 'toolCallId' => null, 'toolName' => null],
                ['role' => 'assistant', 'content' => 'Previous response', 'toolCallId' => null, 'toolName' => null],
            ]);

        $mockMemory
            ->expects($this->once())
            ->method('append')
            ->willReturnCallback(function ($sid, $message) use (&$appendedMessages) {
                $appendedMessages[] = $message;
            });

        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider
            ->expects($this->once())
            ->method('chat')
            ->willReturnCallback(function (array $messages) {
                // Should have history + new user message
                $this->assertCount(3, $messages);
                $this->assertSame('Previous message', $messages[0]->content);
                $this->assertSame('Previous response', $messages[1]->content);
                $this->assertSame('New message', $messages[2]->content);

                return new TextResponse(
                    text: 'New response',
                    usage: new Usage(10, 20),
                    finishReason: 'stop',
                    raw: [],
                );
            });

        $generator = new TextGenerator($mockProvider);
        $options = GenerateTextOptions::make()
            ->withPrompt('New message')
            ->withMemory($mockMemory, $sessionId);

        $response = $generator->generate($options);

        $this->assertSame('New response', $response->getText());

        // Verify assistant response was appended to memory
        $this->assertCount(1, $appendedMessages);
        $this->assertSame('assistant', $appendedMessages[0]['role']);
        $this->assertSame('New response', $appendedMessages[0]['content']);
    }

    public function testDoesNotLoadMemoryWhenNotConfigured(): void
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider
            ->expects($this->once())
            ->method('chat')
            ->willReturnCallback(function (array $messages) {
                // Should only have the new user message, no history
                $this->assertCount(1, $messages);
                $this->assertSame('Hello', $messages[0]->content);

                return new TextResponse(
                    text: 'Response',
                    usage: new Usage(10, 20),
                    finishReason: 'stop',
                    raw: [],
                );
            });

        $generator = new TextGenerator($mockProvider);
        $options = GenerateTextOptions::make()->withPrompt('Hello');

        $generator->generate($options);
    }

    public function testDoesNotSaveMemoryWhenNotConfigured(): void
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider
            ->expects($this->once())
            ->method('chat')
            ->willReturn(new TextResponse(
                text: 'Response',
                usage: new Usage(10, 20),
                finishReason: 'stop',
                raw: [],
            ));

        $generator = new TextGenerator($mockProvider);
        $options = GenerateTextOptions::make()->withPrompt('Hello');

        // Should not throw even without memory configured
        $generator->generate($options);
    }
}
