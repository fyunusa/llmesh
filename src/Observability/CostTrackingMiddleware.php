<?php

declare(strict_types=1);

namespace LLMesh\Core\Observability;

use LLMesh\Core\Contracts\EmbeddingResponseInterface;
use LLMesh\Core\Contracts\ResponseInterface;
use LLMesh\Core\Contracts\StreamInterface;

/**
 * Provider middleware that accumulates token usage and cost across calls.
 */
class CostTrackingMiddleware extends AbstractMiddleware
{
    /**
     * Constructor.
     *
     * @param UsageTracker $tracker Usage tracker instance to accumulate usage into
     */
    public function __construct(
        private readonly UsageTracker $tracker,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function chat(array $messages, array $options = []): ResponseInterface
    {
        $response = $this->next->chat($messages, $options);
        $this->tracker->record($response->getUsage());
        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function stream(array $messages, array $options = []): StreamInterface
    {
        $stream = $this->next->stream($messages, $options);
        return new CostTrackingStream($stream, $this->tracker);
    }

    /**
     * {@inheritDoc}
     */
    public function embed(string|array $input, array $options = []): EmbeddingResponseInterface
    {
        $response = $this->next->embed($input, $options);
        $this->tracker->record($response->getUsage());
        return $response;
    }

    /**
     * Get the underlying usage tracker.
     */
    public function getTracker(): UsageTracker
    {
        return $this->tracker;
    }
}
