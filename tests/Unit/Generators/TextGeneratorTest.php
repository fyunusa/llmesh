<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Generators;

use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Contracts\ResponseInterface;
use LLMesh\Core\Data\Message;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Core\Generators\TextGenerator;
use LLMesh\Core\Generators\TextResponse;
use LLMesh\Core\Generators\Usage;
use PHPUnit\Framework\TestCase;

final class TextGeneratorTest extends TestCase
{
    public function testCanGenerateTextFromPrompt(): void
    {
        // Create a mock provider
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider
            ->expects($this->once())
            ->method('chat')
            ->willReturnCallback(function (array $messages, array $options) {
                // Verify the message was created correctly
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

        $response = $generator->generate($options);

        $this->assertSame('Response', $response->getText());
    }

    public function testCanGenerateTextFromMessages(): void
    {
        $messages = [
            Message::user('Hello'),
            Message::assistant('Hi there!'),
            Message::user('How are you?'),
        ];

        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider
            ->expects($this->once())
            ->method('chat')
            ->willReturnCallback(function (array $passedMessages) use ($messages) {
                // Verify messages were passed correctly
                $this->assertCount(3, $passedMessages);
                return new TextResponse(
                    text: 'Response',
                    usage: new Usage(10, 20),
                    finishReason: 'stop',
                    raw: [],
                );
            });

        $generator = new TextGenerator($mockProvider);
        $options = GenerateTextOptions::make()->withMessages($messages);

        $response = $generator->generate($options);

        $this->assertSame('Response', $response->getText());
    }

    public function testPassesOptionsToProvider(): void
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider
            ->expects($this->once())
            ->method('chat')
            ->willReturnCallback(function (array $messages, array $options) {
                $this->assertSame('You are helpful', $options['system']);
                $this->assertSame(0.7, $options['temperature']);
                $this->assertSame(100, $options['max_tokens']);

                return new TextResponse(
                    text: 'Response',
                    usage: new Usage(10, 20),
                    finishReason: 'stop',
                    raw: [],
                );
            });

        $generator = new TextGenerator($mockProvider);
        $options = GenerateTextOptions::make()
            ->withPrompt('Hello')
            ->withSystem('You are helpful')
            ->withTemperature(0.7)
            ->withMaxTokens(100);

        $generator->generate($options);
    }

    public function testThrowsValidationExceptionWhenNeitherPromptNorMessages(): void
    {
        $mockProvider = $this->createStub(ProviderInterface::class);
        $generator = new TextGenerator($mockProvider);
        $options = GenerateTextOptions::make();

        $this->expectException(\LLMesh\Core\Exceptions\ValidationException::class);
        $generator->generate($options);
    }

    public function testReturnsTextResponse(): void
    {
        $usage = new Usage(100, 50, 150, 0.25);
        $mockResponse = new TextResponse(
            text: 'Generated text',
            usage: $usage,
            finishReason: 'stop',
            raw: ['key' => 'value'],
        );

        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider
            ->expects($this->once())
            ->method('chat')
            ->willReturn($mockResponse);

        $generator = new TextGenerator($mockProvider);
        $options = GenerateTextOptions::make()->withPrompt('Hello');

        $response = $generator->generate($options);

        $this->assertInstanceOf(TextResponse::class, $response);
        $this->assertSame('Generated text', $response->getText());
        $this->assertSame(0.25, $response->getUsage()->getEstimatedCost());
    }
}
