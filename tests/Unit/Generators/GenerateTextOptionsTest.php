<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Generators;

use LLMesh\Core\Exceptions\ValidationException;
use LLMesh\Core\Generators\GenerateTextOptions;
use PHPUnit\Framework\TestCase;

final class GenerateTextOptionsTest extends TestCase
{
    public function testCanCreateWithPrompt(): void
    {
        $options = GenerateTextOptions::make()->withPrompt('Hello');

        $this->assertSame('Hello', $options->prompt);
        $this->assertEmpty($options->messages);
    }

    public function testCanCreateWithMessages(): void
    {
        $messages = [['role' => 'user', 'content' => 'Hello']];
        $options = GenerateTextOptions::make()->withMessages($messages);

        $this->assertSame($messages, $options->messages);
        $this->assertNull($options->prompt);
    }

    public function testCanChainOptions(): void
    {
        $options = GenerateTextOptions::make()
            ->withPrompt('Hello')
            ->withSystem('You are helpful')
            ->withTemperature(0.7)
            ->withMaxTokens(100);

        $this->assertSame('Hello', $options->prompt);
        $this->assertSame('You are helpful', $options->system);
        $this->assertSame(0.7, $options->temperature);
        $this->assertSame(100, $options->maxTokens);
    }

    public function testThrowsValidationExceptionWhenNeitherPromptNorMessages(): void
    {
        $options = GenerateTextOptions::make();

        $this->expectException(ValidationException::class);
        $options->validate();
    }

    public function testValidatesWithPrompt(): void
    {
        $options = GenerateTextOptions::make()->withPrompt('Hello');

        // Should not throw
        $options->validate();
        $this->assertTrue(true);
    }

    public function testValidatesWithMessages(): void
    {
        $messages = [['role' => 'user', 'content' => 'Hello']];
        $options = GenerateTextOptions::make()->withMessages($messages);

        // Should not throw
        $options->validate();
        $this->assertTrue(true);
    }

    public function testPropertiesAreReadonly(): void
    {
        $options = GenerateTextOptions::make()->withPrompt('Hello');

        $this->expectException(\Error::class);
        $options->prompt = 'Modified';
    }

    public function testCanSetStopSequences(): void
    {
        $options = GenerateTextOptions::make()
            ->withPrompt('Hello')
            ->withStopSequences(['stop1', 'stop2']);

        $this->assertSame(['stop1', 'stop2'], $options->stopSequences);
    }

    public function testCanSetTools(): void
    {
        $tools = ['tool1', 'tool2'];
        $options = GenerateTextOptions::make()
            ->withPrompt('Hello')
            ->withTools($tools);

        $this->assertSame($tools, $options->tools);
    }

    public function testDefaultValuesAreCorrect(): void
    {
        $options = GenerateTextOptions::make()->withPrompt('Hello');

        $this->assertNull($options->system);
        $this->assertNull($options->temperature);
        $this->assertNull($options->maxTokens);
        $this->assertEmpty($options->stopSequences);
        $this->assertEmpty($options->tools);
        $this->assertNull($options->memory);
        $this->assertNull($options->sessionId);
    }
}
