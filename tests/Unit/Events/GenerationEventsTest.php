<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Events;

use LLMesh\Core\Events\GenerationCompleted;
use LLMesh\Core\Events\GenerationFailed;
use LLMesh\Core\Events\GenerationStarted;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Core\Generators\TextResponse;
use LLMesh\Core\Generators\Usage;
use PHPUnit\Framework\TestCase;

final class GenerationEventsTest extends TestCase
{
    public function testCanCreateGenerationStartedEvent(): void
    {
        $options = GenerateTextOptions::make()->withPrompt('Hello');
        $event = new GenerationStarted('openai', $options);

        $this->assertSame('openai', $event->provider);
        $this->assertSame($options, $event->options);
    }

    public function testCanCreateGenerationCompletedEvent(): void
    {
        $response = new TextResponse(
            text: 'Generated',
            usage: new Usage(10, 20),
            finishReason: 'stop',
            raw: [],
        );

        $event = new GenerationCompleted('openai', $response, 125);

        $this->assertSame('openai', $event->provider);
        $this->assertSame($response, $event->response);
        $this->assertSame(125, $event->durationMs);
    }

    public function testCanCreateGenerationFailedEvent(): void
    {
        $exception = new \RuntimeException('API Error');
        $event = new GenerationFailed('openai', $exception);

        $this->assertSame('openai', $event->provider);
        $this->assertSame($exception, $event->exception);
    }

    public function testPropertiesAreReadonly(): void
    {
        $event = new GenerationStarted('openai', GenerateTextOptions::make()->withPrompt('Hi'));

        $this->expectException(\Error::class);
        $event->provider = 'anthropic';
    }
}
