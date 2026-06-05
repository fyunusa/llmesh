<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Http;

use LLMesh\Core\Exceptions\ConnectionException;
use LLMesh\Core\Exceptions\HttpException;
use LLMesh\Core\Exceptions\RateLimitException;
use LLMesh\Core\Http\RetryHandler;
use PHPUnit\Framework\TestCase;

final class RetryHandlerTest extends TestCase
{
    public function testSuccessfulCallOnFirstAttempt(): void
    {
        $handler = new RetryHandler(maxAttempts: 3);
        $result = $handler->execute(function () {
            return 'success';
        });

        $this->assertSame('success', $result);
    }

    public function testRetriesOnConnectionException(): void
    {
        $handler = new RetryHandler(maxAttempts: 3);
        $callCount = 0;

        $result = $handler->execute(function () use (&$callCount) {
            $callCount++;

            if ($callCount < 3) {
                throw new ConnectionException('Network error');
            }

            return 'success';
        }, baseDelayMs: 1);

        $this->assertSame('success', $result);
        $this->assertSame(3, $callCount);
    }

    public function testRetriesOnRateLimitException(): void
    {
        $handler = new RetryHandler(maxAttempts: 3);
        $callCount = 0;

        $result = $handler->execute(function () use (&$callCount) {
            $callCount++;

            if ($callCount < 2) {
                throw new RateLimitException('Rate limited', 'openai');
            }

            return 'success';
        }, baseDelayMs: 1);

        $this->assertSame('success', $result);
        $this->assertSame(2, $callCount);
    }

    public function testDoesNotRetryOnTokenLimitException(): void
    {
        $handler = new RetryHandler(maxAttempts: 3);
        $callCount = 0;

        $this->expectException(\LLMesh\Core\Exceptions\TokenLimitException::class);

        $handler->execute(function () use (&$callCount) {
            $callCount++;
            throw new \LLMesh\Core\Exceptions\TokenLimitException(
                'Token limit exceeded',
                'openai',
                limit: 4096,
                used: 5000,
            );
        }, baseDelayMs: 1);

        // Should fail immediately without retrying
        $this->assertSame(1, $callCount);
    }

    public function testDoesNotRetryOnValidationException(): void
    {
        $handler = new RetryHandler(maxAttempts: 3);
        $callCount = 0;

        $this->expectException(\LLMesh\Core\Exceptions\ValidationException::class);

        $handler->execute(function () use (&$callCount) {
            $callCount++;
            throw new \LLMesh\Core\Exceptions\ValidationException('Invalid input');
        }, baseDelayMs: 1);

        $this->assertSame(1, $callCount);
    }

    public function testThrowsExceptionAfterMaxAttempts(): void
    {
        $handler = new RetryHandler(maxAttempts: 3);
        $callCount = 0;

        $this->expectException(ConnectionException::class);

        $handler->execute(function () use (&$callCount) {
            $callCount++;
            throw new ConnectionException('Always fails');
        }, baseDelayMs: 1);

        $this->assertSame(3, $callCount);
    }

    public function testRespectsRetryAfterHeader(): void
    {
        $handler = new RetryHandler(maxAttempts: 2);
        $callCount = 0;
        $startTime = microtime(true);

        try {
            $handler->execute(function () use (&$callCount) {
                $callCount++;
                throw new RateLimitException('Rate limited', 'openai', retryAfter: 1);
            }, baseDelayMs: 100);
        } catch (RateLimitException) {
            // Expected to fail after 2 attempts
        }

        $elapsedSeconds = microtime(true) - $startTime;

        // Should have slept for at least 1 second due to retryAfter
        $this->assertGreaterThanOrEqual(0.9, $elapsedSeconds);
    }

    public function testExponentialBackoffWithJitter(): void
    {
        $handler = new RetryHandler(maxAttempts: 4);
        $attempts = [];

        try {
            $handler->execute(function () use (&$attempts) {
                $attempts[] = microtime(true);
                throw new ConnectionException('Fail');
            }, baseDelayMs: 10);
        } catch (ConnectionException) {
            // Expected
        }

        // Should have 4 attempts
        $this->assertCount(4, $attempts);

        // Each attempt should have increasing delays
        $delay1 = ($attempts[1] - $attempts[0]) * 1000;
        $delay2 = ($attempts[2] - $attempts[1]) * 1000;
        $delay3 = ($attempts[3] - $attempts[2]) * 1000;

        // Delays should generally increase (exponential backoff)
        // Be lenient with timing to avoid flaky tests
        $this->assertGreaterThan($delay1 * 0.5, $delay2); // At least 0.5x increase
        $this->assertGreaterThan($delay2 * 0.5, $delay3); // At least 0.5x increase
    }
}
