<?php

declare(strict_types=1);

namespace LLMesh\Core\Observability;

use LLMesh\Core\Contracts\ProviderInterface;

/**
 * Fluent builder that composes `MiddlewareInterface` layers around a provider.
 *
 * Each call to `with()` wraps the current provider with the given middleware,
 * building an onion of providers. The **last** middleware added is the
 * **outermost** layer (first to receive calls).
 *
 * @example
 * ```php
 * $provider = MiddlewareStack::wrap($rawProvider)
 *     ->with(new LoggingMiddleware($logger))      // inner
 *     ->with(new RetryMiddleware(attempts: 3))    // outer-inner
 *     ->with(new CostTrackingMiddleware($tracker)); // outermost
 * ```
 *
 * This produces the call order:
 *   `CostTracking → Retry → Logging → rawProvider`
 */
final class MiddlewareStack
{
    private function __construct(
        private ProviderInterface $provider,
    ) {
    }

    /**
     * Begin building a middleware stack around the given provider.
     *
     * @param ProviderInterface $provider The inner (real) provider
     */
    public static function wrap(ProviderInterface $provider): self
    {
        return new self($provider);
    }

    /**
     * Add a middleware layer around the current provider.
     *
     * Returns a new `MiddlewareStack` wrapping the middleware around
     * the previously composed provider, so calls can be chained.
     *
     * @param MiddlewareInterface $middleware The middleware to add
     * @return self
     */
    public function with(MiddlewareInterface $middleware): self
    {
        $middleware->setNext($this->provider);

        return new self($middleware);
    }

    /**
     * Return the fully composed provider.
     *
     * The returned object is a `ProviderInterface` — it satisfies the same
     * contract as the raw provider, fully transparent to callers.
     */
    public function build(): ProviderInterface
    {
        return $this->provider;
    }

    /**
     * Allow using the stack directly as a `ProviderInterface` by forwarding all
     * calls to the composed provider — so the caller can use the `MiddlewareStack`
     * return value of `with()` directly without calling `build()`.
     *
     * This is achieved by implementing `ProviderInterface` on the stack itself
     * and forwarding to the inner provider.
     */
    public function chat(array $messages, array $options = []): \LLMesh\Core\Contracts\ResponseInterface
    {
        return $this->provider->chat($messages, $options);
    }

    public function stream(array $messages, array $options = []): \LLMesh\Core\Contracts\StreamInterface
    {
        return $this->provider->stream($messages, $options);
    }

    public function embed(string|array $input, array $options = []): \LLMesh\Core\Contracts\EmbeddingResponseInterface
    {
        return $this->provider->embed($input, $options);
    }

    public function supports(string $capability): bool
    {
        return $this->provider->supports($capability);
    }
}
