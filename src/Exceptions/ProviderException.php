<?php

declare(strict_types=1);

namespace LLMesh\Core\Exceptions;

/**
 * Exception for provider-specific errors.
 */
class ProviderException extends LLMeshException
{
    /**
     * Constructor.
     *
     * @param string $message Error message
     * @param string $provider Provider name
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        private readonly string $provider,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the provider name.
     *
     * @return string
     */
    public function provider(): string
    {
        return $this->provider;
    }
}
