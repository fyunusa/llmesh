<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit;

use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Events\GenerationCompleted;
use LLMesh\Core\Events\GenerationFailed;
use LLMesh\Core\Events\GenerationStarted;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Core\Generators\TextResponse;
use LLMesh\Core\Generators\Usage;
use LLMesh\Core\LLMesh;
use LLMesh\Core\Structured\LLMModel;
use LLMesh\Core\Structured\ExtractionOptions;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;


final class LLMeshTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset event dispatcher between tests
        LLMesh::withEventDispatcher(null);
    }

    public function testCanGenerateText(): void
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider
            ->expects($this->once())
            ->method('chat')
            ->willReturn(new TextResponse(
                text: 'Hello',
                usage: new Usage(10, 20),
                finishReason: 'stop',
                raw: [],
            ));

        $options = GenerateTextOptions::make()->withPrompt('Hi');

        $response = LLMesh::make()->generateText($mockProvider, $options);

        $this->assertSame('Hello', $response->getText());
    }

    public function testDispatchesGenerationStartedEvent(): void
    {
        $startedEvent = null;

        $mockDispatcher = $this->createMock(EventDispatcherInterface::class);
        $mockDispatcher
            ->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$startedEvent) {
                if ($event instanceof GenerationStarted) {
                    $startedEvent = $event;
                }
                return $event;
            });

        LLMesh::withEventDispatcher($mockDispatcher);

        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider
            ->expects($this->once())
            ->method('chat')
            ->willReturn(new TextResponse(
                text: 'Hello',
                usage: new Usage(10, 20),
                finishReason: 'stop',
                raw: [],
            ));

        $options = GenerateTextOptions::make()->withPrompt('Hi');
        LLMesh::generateText($mockProvider, $options);

        $this->assertNotNull($startedEvent);
        $this->assertInstanceOf(GenerationStarted::class, $startedEvent);
        $this->assertSame('Mock', $startedEvent->provider);
        $this->assertSame($options, $startedEvent->options);
    }

    public function testDispatchesGenerationCompletedEvent(): void
    {
        $completedEvent = null;

        $mockDispatcher = $this->createMock(EventDispatcherInterface::class);
        $mockDispatcher
            ->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$completedEvent) {
                if ($event instanceof GenerationCompleted) {
                    $completedEvent = $event;
                }
                return $event;
            });

        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider
            ->expects($this->once())
            ->method('chat')
            ->willReturn(new TextResponse(
                text: 'Hello',
                usage: new Usage(10, 20),
                finishReason: 'stop',
                raw: [],
            ));

        $options = GenerateTextOptions::make()->withPrompt('Hi');
        LLMesh::withEventDispatcher($mockDispatcher)->generateText($mockProvider, $options);

        $this->assertNotNull($completedEvent);
        $this->assertInstanceOf(GenerationCompleted::class, $completedEvent);
        $this->assertSame('Mock', $completedEvent->provider);
        $this->assertInstanceOf(TextResponse::class, $completedEvent->response);
        $this->assertGreaterThanOrEqual(0, $completedEvent->durationMs);
    }

    public function testDispatchesGenerationFailedEvent(): void
    {
        $failedEvent = null;

        $mockDispatcher = $this->createMock(EventDispatcherInterface::class);
        $mockDispatcher
            ->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$failedEvent) {
                if ($event instanceof GenerationFailed) {
                    $failedEvent = $event;
                }
                return $event;
            });

        $exception = new \RuntimeException('Provider error');
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider
            ->expects($this->once())
            ->method('chat')
            ->willThrowException($exception);

        $options = GenerateTextOptions::make()->withPrompt('Hi');

        $this->expectException(\RuntimeException::class);
        LLMesh::withEventDispatcher($mockDispatcher)->generateText($mockProvider, $options);

        $this->assertNotNull($failedEvent);
        $this->assertInstanceOf(GenerationFailed::class, $failedEvent);
        $this->assertSame('Mock', $failedEvent->provider);
        $this->assertSame($exception, $failedEvent->exception);
    }

    public function testWorksWithoutEventDispatcher(): void
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider
            ->expects($this->once())
            ->method('chat')
            ->willReturn(new TextResponse(
                text: 'Hello',
                usage: new Usage(10, 20),
                finishReason: 'stop',
                raw: [],
            ));

        $options = GenerateTextOptions::make()->withPrompt('Hi');

        // Should not throw even without dispatcher
        $response = LLMesh::make()->generateText($mockProvider, $options);

        $this->assertSame('Hello', $response->getText());
    }

    public function testCanExtract(): void
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider->expects($this->once())
            ->method('chat')
            ->willReturn(new TextResponse(
                text: json_encode(['result' => 'extracted-facade']),
                usage: new Usage(10, 20),
                finishReason: 'stop',
                raw: []
            ));

        $options = ExtractionOptions::make()
            ->withInput('source text')
            ->into(LLMeshTestModel::class);

        /** @var LLMeshTestModel $model */
        $model = LLMesh::make()->extract($mockProvider, $options);

        $this->assertInstanceOf(LLMeshTestModel::class, $model);
        $this->assertSame('extracted-facade', $model->result);
    }

    public function testCanExtractFromShorthand(): void
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider->expects($this->once())
            ->method('chat')
            ->willReturn(new TextResponse(
                text: json_encode(['result' => 'shorthand-facade']),
                usage: new Usage(10, 20),
                finishReason: 'stop',
                raw: []
            ));

        /** @var LLMeshTestModel $model */
        $model = LLMesh::make()->extractFrom(LLMeshTestModel::class, 'source text', $mockProvider);

        $this->assertInstanceOf(LLMeshTestModel::class, $model);
        $this->assertSame('shorthand-facade', $model->result);
    }
}

class LLMeshTestModel extends LLMModel
{
    public function __construct(
        public readonly string $result,
    ) {}
}

