<?php

declare(strict_types=1);

namespace LLMesh\Core\Exceptions;

/**
 * Exception for HTTP-level errors.
 */
class HttpException extends LLMeshException
{
    /**
     * Constructor.
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param string $responseBody Response body content
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $responseBody,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the response body.
     *
     * @return string
     */
    public function responseBody(): string
    {
        return $this->responseBody;
    }
}
