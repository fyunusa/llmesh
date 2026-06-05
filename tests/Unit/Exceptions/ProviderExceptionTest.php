<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Exceptions;

use LLMesh\Core\Exceptions\ProviderException;
use LLMesh\Core\Exceptions\RateLimitException;
use LLMesh\Core\Exceptions\TokenLimitException;
use PHPUnit\Framework\TestCase;

final class ProviderExceptionTest extends TestCase
{
    public function testCanThrowProviderException(): void
    {
        $exception = new ProviderException('API error', 'openai');

        $this->assertSame('API error', $exception->getMessage());
        $this->assertSame('openai', $exception->provider());
    }

    public function testCanThrowRateLimitException(): void
    {
        $exception = new RateLimitException('Rate limited', 'openai', 60);

        $this->assertSame('Rate limited', $exception->getMessage());
        $this->assertSame('openai', $exception->provider());
        $this->assertSame(60, $exception->retryAfter());
    }

    public function testRateLimitExceptionWithoutRetryAfter(): void
    {
        $exception = new RateLimitException('Rate limited', 'anthropic');

        $this->assertNull($exception->retryAfter());
    }

    public function testCanThrowTokenLimitException(): void
    {
        $exception = new TokenLimitException(
            'Context length exceeded',
            'openai',
            limit: 4096,
            used: 5000,
        );

        $this->assertSame('Context length exceeded', $exception->getMessage());
        $this->assertSame('openai', $exception->provider());
        $this->assertSame(4096, $exception->limit());
        $this->assertSame(5000, $exception->used());
    }

    public function testExceptionHierarchy(): void
    {
        $rateLimitException = new RateLimitException('error', 'openai');
        $this->assertInstanceOf(ProviderException::class, $rateLimitException);

        $tokenLimitException = new TokenLimitException('error', 'openai', 100, 150);
        $this->assertInstanceOf(ProviderException::class, $tokenLimitException);
    }
}
