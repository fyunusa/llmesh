<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Exceptions;

use LLMesh\Core\Exceptions\ToolExecutionException;
use LLMesh\Core\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class OtherExceptionsTest extends TestCase
{
    public function testCanThrowToolExecutionException(): void
    {
        $exception = new ToolExecutionException('Tool failed', 'get_weather');

        $this->assertSame('Tool failed', $exception->getMessage());
        $this->assertSame('get_weather', $exception->toolName());
    }

    public function testCanThrowValidationException(): void
    {
        $errors = ['name' => 'Name is required', 'age' => 'Age must be positive'];
        $exception = new ValidationException('Validation failed', $errors);

        $this->assertSame('Validation failed', $exception->getMessage());
        $this->assertSame($errors, $exception->errors());
    }

    public function testValidationExceptionWithoutErrors(): void
    {
        $exception = new ValidationException('Validation error');

        $this->assertSame([], $exception->errors());
    }
}
