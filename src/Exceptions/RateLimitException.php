<?php

declare(strict_types=1);

namespace LLMesh\Core\Exceptions;

/**
 * Exception when rate limited by provider.
 */
class RateLimitException extends ProviderException
{
    /**
     * Constructor.
     *
     * @param string $message Error message
     * @param string $provider Provider name
     * @param int|null $retryAfter Seconds to wait before retrying
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        string $provider,
        private readonly ?int $retryAfter = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $provider, $code, $previous);
    }

    /**
     * Get the number of seconds to wait before retrying.
     *
     * @return int|null Seconds to wait, or null if not specified
     */
    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
