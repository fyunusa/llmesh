<?php

declare(strict_types=1);

namespace LLMesh\Core\Exceptions;

/**
 * Exception for validation errors.
 */
class ValidationException extends LLMeshException
{
    /**
     * Constructor.
     *
     * @param string $message Error message
     * @param array $errors Array of validation errors
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        private readonly array $errors = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get validation errors.
     *
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
