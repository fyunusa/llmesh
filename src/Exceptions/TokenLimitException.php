<?php

declare(strict_types=1);

namespace LLMesh\Core\Exceptions;

/**
 * Exception when token limit is exceeded.
 */
class TokenLimitException extends ProviderException
{
    /**
     * Constructor.
     *
     * @param string $message Error message
     * @param string $provider Provider name
     * @param int $limit Token limit
     * @param int $used Tokens used
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        string $provider,
        private readonly int $limit,
        private readonly int $used,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $provider, $code, $previous);
    }

    /**
     * Get the token limit.
     *
     * @return int
     */
    public function limit(): int
    {
        return $this->limit;
    }

    /**
     * Get the number of tokens used.
     *
     * @return int
     */
    public function used(): int
    {
        return $this->used;
    }
}
