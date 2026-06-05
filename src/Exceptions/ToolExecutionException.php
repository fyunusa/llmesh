<?php

declare(strict_types=1);

namespace LLMesh\Core\Exceptions;

/**
 * Exception when tool execution fails.
 */
class ToolExecutionException extends LLMeshException
{
    /**
     * Constructor.
     *
     * @param string $message Error message
     * @param string $toolName Name of the tool
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        private readonly string $toolName,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the tool name.
     *
     * @return string
     */
    public function toolName(): string
    {
        return $this->toolName;
    }
}
