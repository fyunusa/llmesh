<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Structured;

use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Generators\TextResponse;
use LLMesh\Core\Generators\Usage;
use LLMesh\Core\Structured\LLMModel;
use LLMesh\Core\Structured\ExtractionOptions;
use LLMesh\Core\Structured\ExtractionGenerator;
use LLMesh\Core\Exceptions\ValidationException;
use Psr\EventDispatcher\EventDispatcherInterface;
use LLMesh\Core\Events\ExtractionStarted;
use LLMesh\Core\Events\ExtractionCompleted;
use LLMesh\Core\Events\ExtractionRetrying;
use LLMesh\Core\Events\ExtractionFailed;
use PHPUnit\Framework\TestCase;

class ExtrModel extends LLMModel
{
    public function __construct(
        public readonly string $name,
        public readonly int $value,
    ) {}

    public function validate(): void
    {
        if ($this->value < 0) {
            throw new \InvalidArgumentException('Value must be positive');
        }
    }
}

final class ExtractionGeneratorTest extends TestCase
{
    public function testSuccessfulExtractionFirstAttempt(): void
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider->expects($this->once())
            ->method('chat')
            ->willReturn(new TextResponse(
                text: json_encode(['name' => 'First Try', 'value' => 42]),
                usage: new Usage(10, 20),
                finishReason: 'stop',
                raw: []
            ));

        $dispatchedEvents = [];
        $mockDispatcher = $this->createMock(EventDispatcherInterface::class);
        $mockDispatcher->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $generator = new ExtractionGenerator($mockDispatcher);
        $options = ExtractionOptions::make()
            ->withInput('hello')
            ->into(ExtrModel::class);

        /** @var ExtrModel $result */
        $result = $generator->extract($mockProvider, $options);

        $this->assertInstanceOf(ExtrModel::class, $result);
        $this->assertSame('First Try', $result->name);
        $this->assertSame(42, $result->value);

        // Verify events
        $this->assertCount(2, $dispatchedEvents);
        $this->assertInstanceOf(ExtractionStarted::class, $dispatchedEvents[0]);
        $this->assertInstanceOf(ExtractionCompleted::class, $dispatchedEvents[1]);
        $this->assertSame(1, $dispatchedEvents[1]->attemptsUsed);
    }

    public function testRetryOnInvalidJsonSucceedsOnSecondAttempt(): void
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider->expects($this->exactly(2))
            ->method('chat')
            ->willReturnOnConsecutiveCalls(
                new TextResponse(
                    text: 'this is not json',
                    usage: new Usage(10, 20),
                    finishReason: 'stop',
                    raw: []
                ),
                new TextResponse(
                    text: json_encode(['name' => 'Second Try', 'value' => 100]),
                    usage: new Usage(10, 20),
                    finishReason: 'stop',
                    raw: []
                )
            );

        $dispatchedEvents = [];
        $mockDispatcher = $this->createMock(EventDispatcherInterface::class);
        $mockDispatcher->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $generator = new ExtractionGenerator($mockDispatcher);
        $options = ExtractionOptions::make()
            ->withInput('hello')
            ->into(ExtrModel::class)
            ->withMaxRetries(3);

        /** @var ExtrModel $result */
        $result = $generator->extract($mockProvider, $options);

        $this->assertInstanceOf(ExtrModel::class, $result);
        $this->assertSame('Second Try', $result->name);
        $this->assertSame(100, $result->value);

        // Verify events
        $this->assertCount(3, $dispatchedEvents);
        $this->assertInstanceOf(ExtractionStarted::class, $dispatchedEvents[0]);
        $this->assertInstanceOf(ExtractionRetrying::class, $dispatchedEvents[1]);
        $this->assertSame(1, $dispatchedEvents[1]->attempt);
        $this->assertStringContainsString('Invalid JSON', $dispatchedEvents[1]->errorMessage);
        $this->assertInstanceOf(ExtractionCompleted::class, $dispatchedEvents[2]);
        $this->assertSame(2, $dispatchedEvents[2]->attemptsUsed);
    }

    public function testRetryOnValidationFailureSucceedsOnSecondAttempt(): void
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider->expects($this->exactly(2))
            ->method('chat')
            ->willReturnOnConsecutiveCalls(
                new TextResponse(
                    text: json_encode(['name' => 'Bad value', 'value' => -5]), // fails validate()
                    usage: new Usage(10, 20),
                    finishReason: 'stop',
                    raw: []
                ),
                new TextResponse(
                    text: json_encode(['name' => 'Good value', 'value' => 5]), // passes
                    usage: new Usage(10, 20),
                    finishReason: 'stop',
                    raw: []
                )
            );

        $dispatchedEvents = [];
        $mockDispatcher = $this->createMock(EventDispatcherInterface::class);
        $mockDispatcher->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $generator = new ExtractionGenerator($mockDispatcher);
        $options = ExtractionOptions::make()
            ->withInput('hello')
            ->into(ExtrModel::class)
            ->withMaxRetries(3);

        /** @var ExtrModel $result */
        $result = $generator->extract($mockProvider, $options);

        $this->assertInstanceOf(ExtrModel::class, $result);
        $this->assertSame('Good value', $result->name);
        $this->assertSame(5, $result->value);

        // Verify event error details
        $this->assertInstanceOf(ExtractionRetrying::class, $dispatchedEvents[1]);
        $this->assertStringContainsString('Model validation failed', $dispatchedEvents[1]->errorMessage);
    }

    public function testExhaustedRetriesThrowsValidationException(): void
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider->expects($this->exactly(3))
            ->method('chat')
            ->willReturn(new TextResponse(
                text: 'always bad json',
                usage: new Usage(10, 20),
                finishReason: 'stop',
                raw: []
            ));

        $dispatchedEvents = [];
        $mockDispatcher = $this->createMock(EventDispatcherInterface::class);
        $mockDispatcher->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $generator = new ExtractionGenerator($mockDispatcher);
        $options = ExtractionOptions::make()
            ->withInput('hello')
            ->into(ExtrModel::class)
            ->withMaxRetries(3);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Extraction failed after 3 attempts');

        try {
            $generator->extract($mockProvider, $options);
        } finally {
            $this->assertCount(4, $dispatchedEvents);
            $this->assertInstanceOf(ExtractionStarted::class, $dispatchedEvents[0]);
            $this->assertInstanceOf(ExtractionRetrying::class, $dispatchedEvents[1]);
            $this->assertInstanceOf(ExtractionRetrying::class, $dispatchedEvents[2]);
            $this->assertInstanceOf(ExtractionFailed::class, $dispatchedEvents[3]);
        }
    }

    public function testStripsCodeFencesCorrectly(): void
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider->expects($this->once())
            ->method('chat')
            ->willReturn(new TextResponse(
                text: "```json\n" . json_encode(['name' => 'Fenced JSON', 'value' => 99]) . "\n```",
                usage: new Usage(10, 20),
                finishReason: 'stop',
                raw: []
            ));

        $generator = new ExtractionGenerator();
        $options = ExtractionOptions::make()
            ->withInput('hello')
            ->into(ExtrModel::class);

        /** @var ExtrModel $result */
        $result = $generator->extract($mockProvider, $options);
        $this->assertSame('Fenced JSON', $result->name);
    }
}
