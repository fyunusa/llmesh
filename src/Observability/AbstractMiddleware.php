<?php

declare(strict_types=1);

namespace LLMesh\Core\Observability;

use LLMesh\Core\Contracts\EmbeddingResponseInterface;
use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Contracts\ResponseInterface;
use LLMesh\Core\Contracts\StreamInterface;

/**
 * Abstract base for provider middleware implementations.
 *
 * Provides the `$next` provider storage and default pass-through
 * implementations for all `ProviderInterface` methods. Subclasses only
 * need to override the specific methods they want to intercept.
 */
abstract class AbstractMiddleware implements MiddlewareInterface
{
    protected ProviderInterface $next;

    /**
     * {@inheritDoc}
     */
    public function setNext(ProviderInterface $next): void
    {
        $this->next = $next;
    }

    /**
     * {@inheritDoc}
     */
    public function chat(array $messages, array $options = []): ResponseInterface
    {
        return $this->next->chat($messages, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function stream(array $messages, array $options = []): StreamInterface
    {
        return $this->next->stream($messages, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function embed(string|array $input, array $options = []): EmbeddingResponseInterface
    {
        return $this->next->embed($input, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function embedBatch(array $inputs, array $options = []): array
    {
        return $this->next->embedBatch($inputs, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $capability): bool
    {
        return $this->next->supports($capability);
    }
}
