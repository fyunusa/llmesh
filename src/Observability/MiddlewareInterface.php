<?php

declare(strict_types=1);

namespace LLMesh\Core\Observability;

use LLMesh\Core\Contracts\EmbeddingResponseInterface;
use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Contracts\ResponseInterface;
use LLMesh\Core\Contracts\StreamInterface;

/**
 * Interface for provider middleware.
 *
 * Each middleware wraps a `ProviderInterface` and must forward
 * all calls (chat, stream, embed, supports) to the next provider in the stack.
 *
 * Implementations may add before/after logic around any of those calls while
 * still fully satisfying `ProviderInterface` so the stack itself is transparent.
 */
interface MiddlewareInterface extends ProviderInterface
{
    /**
     * Inject the next provider (the inner layer) into this middleware.
     *
     * Called automatically by `MiddlewareStack` during construction.
     *
     * @param ProviderInterface $next The wrapped provider
     */
    public function setNext(ProviderInterface $next): void;
}
