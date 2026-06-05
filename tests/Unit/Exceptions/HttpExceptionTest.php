<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Exceptions;

use LLMesh\Core\Exceptions\HttpException;
use LLMesh\Core\Exceptions\LLMeshException;
use PHPUnit\Framework\TestCase;

final class HttpExceptionTest extends TestCase
{
    public function testCanThrowHttpException(): void
    {
        $exception = new HttpException(
            'Request failed',
            429,
            'Rate limited',
        );

        $this->assertSame('Request failed', $exception->getMessage());
        $this->assertSame(429, $exception->statusCode());
        $this->assertSame('Rate limited', $exception->responseBody());
    }

    public function testHttpExceptionExtendsLLMeshException(): void
    {
        $exception = new HttpException('error', 500, 'body');
        $this->assertInstanceOf(LLMeshException::class, $exception);
    }

    public function testHttpExceptionCanHavePreviousException(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new HttpException('New error', 500, 'body', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testCanAccessStatusCodeAndBody(): void
    {
        $exception = new HttpException(
            'Bad Request',
            400,
            '{"error": "Invalid input"}',
        );

        $this->assertSame(400, $exception->statusCode());
        $this->assertStringContainsString('Invalid input', $exception->responseBody());
    }
}
