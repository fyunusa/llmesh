<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Observability;

use LLMesh\Core\Contracts\EmbeddingResponseInterface;
use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Contracts\ResponseInterface;
use LLMesh\Core\Contracts\StreamInterface;
use LLMesh\Core\Contracts\UsageInterface;
use LLMesh\Core\Exceptions\ConnectionException;
use LLMesh\Core\Exceptions\RateLimitException;
use LLMesh\Core\Generators\Usage;
use LLMesh\Core\Observability\CostCalculator;
use LLMesh\Core\Observability\CostTrackingMiddleware;
use LLMesh\Core\Observability\LoggingMiddleware;
use LLMesh\Core\Observability\MiddlewareStack;
use LLMesh\Core\Observability\RequestLogger;
use LLMesh\Core\Observability\RetryMiddleware;
use LLMesh\Core\Observability\UsageTracker;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Mockery;

final class ObservabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CostCalculator::resetPricing();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function testCostCalculatorCalculatesCorrectly(): void
    {
        // gpt-4o: $2.50 input / $10.00 output per 1M tokens
        // 1000 input, 500 output
        // input cost = (1000 / 1_000_000) * 2.50 = 0.0025
        // output cost = (500 / 1_000_000) * 10.00 = 0.0050
        // total = 0.0075
        $cost = CostCalculator::calculate('gpt-4o', 1000, 500);
        $this->assertSame(0.0075, $cost);
    }

    public function testCostCalculatorReturnsNullForUnknownModel(): void
    {
        $cost = CostCalculator::calculate('non-existent-model', 1000, 500);
        $this->assertNull($cost);
    }

    public function testCostCalculatorIsKnownModel(): void
    {
        $this->assertTrue(CostCalculator::isKnownModel('gpt-4o'));
        $this->assertFalse(CostCalculator::isKnownModel('non-existent-model'));
    }

    public function testCostCalculatorAllowsOverridingPricing(): void
    {
        CostCalculator::setPricing('custom-model', 5.00, 15.00);
        $this->assertTrue(CostCalculator::isKnownModel('custom-model'));

        // 1000 input, 1000 output -> (1000/1M * 5) + (1000/1M * 15) = 0.005 + 0.015 = 0.02
        $cost = CostCalculator::calculate('custom-model', 1000, 1000);
        $this->assertSame(0.02, $cost);
    }

    public function testUsageTrackerAccumulatesCorrectly(): void
    {
        $tracker = new UsageTracker();

        $usage1 = new Usage(100, 50, 150, 0.001);
        $usage2 = new Usage(200, 100, 300, 0.002);
        $usage3 = new Usage(300, 150, 450, 0.003);

        $tracker->record($usage1);
        $tracker->record($usage2);
        $tracker->record($usage3);

        $this->assertSame(600, $tracker->getTotalInputTokens());
        $this->assertSame(300, $tracker->getTotalOutputTokens());
        $this->assertSame(900, $tracker->getTotalTokens());
        $this->assertSame(0.006, $tracker->getTotalCost());
        $this->assertSame(3, $tracker->getCallCount());
        $this->assertCount(3, $tracker->getRecords());

        $summary = $tracker->getSummary();
        $this->assertSame([
            'calls' => 3,
            'tokens_in' => 600,
            'tokens_out' => 300,
            'total_tokens' => 900,
            'cost_usd' => 0.006,
        ], $summary);

        $tracker->reset();
        $this->assertSame(0, $tracker->getTotalInputTokens());
        $this->assertSame(0, $tracker->getTotalOutputTokens());
        $this->assertSame(0.0, $tracker->getTotalCost());
        $this->assertSame(0, $tracker->getCallCount());
        $this->assertEmpty($tracker->getRecords());
    }

    public function testLoggingMiddlewareLogsCorrectly(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger
            ->expects($this->once())
            ->method('debug')
            ->with(
                $this->equalTo('LLMesh request completed'),
                $this->callback(function (array $context) {
                    return $context['provider'] === 'Mock'
                        && $context['model'] === 'gpt-4o'
                        && $context['tokens_in'] === 10
                        && $context['tokens_out'] === 20
                        && $context['cost_usd'] === 0.000225
                        && $context['status'] === 'success'
                        && isset($context['duration_ms']);
                })
            );

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse
            ->method('getUsage')
            ->willReturn(new Usage(10, 20, 30, 0.000225));

        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider
            ->expects($this->once())
            ->method('chat')
            ->willReturn($mockResponse);

        $middleware = new LoggingMiddleware($mockLogger, 'Mock', 'gpt-4o');
        $middleware->setNext($mockProvider);

        $response = $middleware->chat([]);
        $this->assertSame($mockResponse, $response);
    }

    public function testLoggingMiddlewareSwallowsLoggerExceptions(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger
            ->method('debug')
            ->willThrowException(new \RuntimeException('Logger failed'));

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse
            ->method('getUsage')
            ->willReturn(new Usage(10, 20, 30, 0.000225));

        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider
            ->method('chat')
            ->willReturn($mockResponse);

        $middleware = new LoggingMiddleware($mockLogger, 'Mock', 'gpt-4o');
        $middleware->setNext($mockProvider);

        // Should not throw even if logger fails
        $response = $middleware->chat([]);
        $this->assertSame($mockResponse, $response);
    }

    public function testRetryMiddlewareRetriesOnRateLimitAndSucceeds(): void
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);

        $mockProvider
            ->expects($this->exactly(3))
            ->method('chat')
            ->willReturnCallback(new class ($mockResponse) {
                private int $calls = 0;
                public function __construct(private ResponseInterface $mockResponse)
                {
                }
                public function __invoke()
                {
                    $this->calls++;
                    if ($this->calls === 1) {
                        throw new RateLimitException('Rate limit exceeded', 'Mock', 1);
                    }
                    if ($this->calls === 2) {
                        throw new RateLimitException('Rate limit exceeded', 'Mock', null);
                    }
                    return $this->mockResponse;
                }
            });

        $middleware = new class (3, 10) extends RetryMiddleware {
            public int $slept = 0;
            public array $sleepTimes = [];
            protected function sleep(int $delayMs): void
            {
                $this->slept++;
                $this->sleepTimes[] = $delayMs;
            }
        };
        $middleware->setNext($mockProvider);

        $response = $middleware->chat([]);
        $this->assertSame($mockResponse, $response);
        $this->assertSame(2, $middleware->slept);
        // First delay should be 1000ms because retry-after is 1
        $this->assertSame(1000, $middleware->sleepTimes[0]);
    }

    public function testRetryMiddlewareThrowsWhenMaxAttemptsReached(): void
    {
        $mockProvider = $this->createMock(ProviderInterface::class);

        $mockProvider
            ->expects($this->exactly(2))
            ->method('chat')
            ->willThrowException(new ConnectionException('Connection failed'));

        $middleware = new class (2, 10) extends RetryMiddleware {
            public int $slept = 0;
            protected function sleep(int $delayMs): void
            {
                $this->slept++;
            }
        };
        $middleware->setNext($mockProvider);

        $this->expectException(ConnectionException::class);
        $middleware->chat([]);
    }

    public function testCostTrackingMiddlewareAccumulatesCost(): void
    {
        $tracker = new UsageTracker();

        $mockResponse1 = $this->createMock(ResponseInterface::class);
        $mockResponse1
            ->method('getUsage')
            ->willReturn(new Usage(100, 50, 150, 0.001));

        $mockResponse2 = $this->createMock(ResponseInterface::class);
        $mockResponse2
            ->method('getUsage')
            ->willReturn(new Usage(200, 100, 300, 0.002));

        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider
            ->expects($this->exactly(2))
            ->method('chat')
            ->willReturnCallback(new class ($mockResponse1, $mockResponse2) {
                private int $calls = 0;
                public function __construct(private ResponseInterface $r1, private ResponseInterface $r2)
                {
                }
                public function __invoke()
                {
                    $this->calls++;
                    return $this->calls === 1 ? $this->r1 : $this->r2;
                }
            });

        $middleware = new CostTrackingMiddleware($tracker);
        $middleware->setNext($mockProvider);

        $middleware->chat([]);
        $middleware->chat([]);

        $this->assertSame(0.003, $tracker->getTotalCost());
        $this->assertSame(300, $tracker->getTotalInputTokens());
        $this->assertSame(150, $tracker->getTotalOutputTokens());
    }

    public function testCostTrackingMiddlewareWithStreams(): void
    {
        $tracker = new UsageTracker();

        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream
            ->method('getUsage')
            ->willReturn(new Usage(50, 25, 75, 0.0005));

        // Mock Iterator interface methods
        $mockStream->method('valid')->willReturnOnConsecutiveCalls(true, false);
        $mockStream->method('current')->willReturn(null);
        $mockStream->method('key')->willReturn(0);

        $mockStream->expects($this->once())->method('getChunks')->willReturn((static function () {
            yield 'chunk';
        })());

        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider
            ->expects($this->once())
            ->method('stream')
            ->willReturn($mockStream);

        $middleware = new CostTrackingMiddleware($tracker);
        $middleware->setNext($mockProvider);

        $stream = $middleware->stream([]);
        $this->assertInstanceOf(StreamInterface::class, $stream);

        // Exhaust stream
        iterator_to_array($stream->getChunks());

        $this->assertSame(0.0005, $tracker->getTotalCost());
        $this->assertSame(50, $tracker->getTotalInputTokens());
        $this->assertSame(25, $tracker->getTotalOutputTokens());
    }

    public function testMiddlewareStackExecutionOrderAndCallChain(): void
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockProvider
            ->expects($this->once())
            ->method('chat')
            ->willReturn($mockResponse);

        $order = [];

        $middleware1 = new class ($order) extends RetryMiddleware {
            private array $ord;
            public function __construct(array &$ord)
            {
                parent::__construct();
                $this->ord = &$ord;
            }
            public function chat(array $messages, array $options = []): ResponseInterface
            {
                $this->ord[] = 'middleware1_before';
                $res = parent::chat($messages, $options);
                $this->ord[] = 'middleware1_after';
                return $res;
            }
        };

        $middleware2 = new class ($order) extends RetryMiddleware {
            private array $ord;
            public function __construct(array &$ord)
            {
                parent::__construct();
                $this->ord = &$ord;
            }
            public function chat(array $messages, array $options = []): ResponseInterface
            {
                $this->ord[] = 'middleware2_before';
                $res = parent::chat($messages, $options);
                $this->ord[] = 'middleware2_after';
                return $res;
            }
        };

        // Composes layers: outermost runs first
        // If we wrap the provider in middleware1 first, then middleware2:
        // middleware2 -> middleware1 -> provider
        $wrapped = MiddlewareStack::wrap($mockProvider)
            ->with($middleware1)
            ->with($middleware2);

        $this->assertInstanceOf(ProviderInterface::class, $wrapped);

        $res = $wrapped->chat([]);
        $this->assertSame($mockResponse, $res);

        // Outer (middleware2) should run before inner (middleware1)
        $this->assertSame([
            'middleware2_before',
            'middleware1_before',
            'middleware1_after',
            'middleware2_after',
        ], $order);
    }

    // Test: CostCalculator pricing entries have correct values with documented rates
    public function testCostCalculatorPricingEntriesAreCorrectlyDocumented(): void
    {
        // gpt-4o: $2.50/1M input, $10.00/1M output
        // 1M input tokens = $2.50
        $this->assertEqualsWithDelta(2.50, CostCalculator::calculate('gpt-4o', 1_000_000, 0), 0.001);

        // 1M output tokens = $10.00
        $this->assertEqualsWithDelta(10.00, CostCalculator::calculate('gpt-4o', 0, 1_000_000), 0.001);

        // text-embedding-3-small: $0.02/1M input, $0.00 output
        $this->assertEqualsWithDelta(0.02, CostCalculator::calculate('text-embedding-3-small', 1_000_000, 0), 0.001);
        $this->assertEqualsWithDelta(0.00, CostCalculator::calculate('text-embedding-3-small', 0, 1_000_000), 0.001);
    }

    // Test: MiddlewareStack implements ProviderInterface
    public function testMiddlewareStackImplementsProviderInterface(): void
    {
        $provider = Mockery::mock(ProviderInterface::class);
        $stack = MiddlewareStack::wrap($provider);

        $this->assertInstanceOf(ProviderInterface::class, $stack);
    }

    // Test: MiddlewareStack can be passed directly as ProviderInterface without calling build()
    public function testMiddlewareStackCanBeUsedDirectlyAsProvider(): void
    {
        $mockResponse = Mockery::mock(ResponseInterface::class);

        $provider = Mockery::mock(ProviderInterface::class);
        $provider->shouldReceive('chat')
            ->once()
            ->andReturn($mockResponse);

        $stack = MiddlewareStack::wrap($provider);

        // Direct call without ->build()
        $result = $stack->chat([['role' => 'user', 'content' => 'hello']], []);

        $this->assertSame($mockResponse, $result);
    }

    // Test: embedBatch calls are logged by LoggingMiddleware
    public function testLoggingMiddlewareLogsEmbedBatchCalls(): void
    {
        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('debug')
            ->once()
            ->with(
                'llmesh.embedding',
                Mockery::on(function (array $context) {
                    return str_contains($context['provider'], 'ProviderInterface')
                        && $context['model'] === 'gpt-4o'
                        && $context['input_count'] === 3
                        && isset($context['duration_ms'])
                        && $context['status'] === 'success';
                })
            );

        $mockResponse = Mockery::mock(EmbeddingResponseInterface::class);

        $provider = Mockery::mock(ProviderInterface::class);
        $provider->shouldReceive('embedBatch')
            ->once()
            ->andReturn([$mockResponse, $mockResponse, $mockResponse]);

        $middleware = new LoggingMiddleware($mockLogger);
        $middleware->setNext($provider);

        $middleware->embedBatch(['a', 'b', 'c'], ['model' => 'gpt-4o']);

        $this->assertTrue(true);
    }

    // Test: embedBatch calls are retried by RetryMiddleware on RateLimitException
    public function testRetryMiddlewareRetriesEmbedBatchOnRateLimit(): void
    {
        $callCount = 0;

        $provider = Mockery::mock(ProviderInterface::class);
        $provider->shouldReceive('embedBatch')
            ->times(3)
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                if ($callCount < 3) {
                    throw new RateLimitException('Rate limited', 'test', null);
                }
                return [Mockery::mock(EmbeddingResponseInterface::class)];
            });

        $middleware = new RetryMiddleware(maxAttempts: 3, baseDelayMs: 0);
        $middleware->setNext($provider);

        $results = $middleware->embedBatch(['input'], []);

        $this->assertCount(1, $results);
        $this->assertEquals(3, $callCount);
    }

    // Test: CostTrackingMiddleware records usage for each embedBatch response
    public function testCostTrackingMiddlewareTracksEmbedBatchUsage(): void
    {
        $tracker = new UsageTracker();

        $mockUsage1 = Mockery::mock(UsageInterface::class);
        $mockUsage1->shouldReceive('getInputTokens')->andReturn(100);
        $mockUsage1->shouldReceive('getOutputTokens')->andReturn(0);
        $mockUsage1->shouldReceive('getTotalTokens')->andReturn(100);
        $mockUsage1->shouldReceive('getEstimatedCost')->andReturn(0.002);

        $mockUsage2 = Mockery::mock(UsageInterface::class);
        $mockUsage2->shouldReceive('getInputTokens')->andReturn(200);
        $mockUsage2->shouldReceive('getOutputTokens')->andReturn(0);
        $mockUsage2->shouldReceive('getTotalTokens')->andReturn(200);
        $mockUsage2->shouldReceive('getEstimatedCost')->andReturn(0.004);

        $response1 = Mockery::mock(EmbeddingResponseInterface::class);
        $response1->shouldReceive('getUsage')->andReturn($mockUsage1);

        $response2 = Mockery::mock(EmbeddingResponseInterface::class);
        $response2->shouldReceive('getUsage')->andReturn($mockUsage2);

        $provider = Mockery::mock(ProviderInterface::class);
        $provider->shouldReceive('embedBatch')
            ->once()
            ->andReturn([$response1, $response2]);

        $middleware = new CostTrackingMiddleware($tracker);
        $middleware->setNext($provider);

        $middleware->embedBatch(['input 1', 'input 2'], []);

        $this->assertEquals(300, $tracker->getTotalInputTokens());
        $this->assertEqualsWithDelta(0.006, $tracker->getTotalCost(), 0.0001);
    }
}
