<?php

declare(strict_types=1);

namespace LLMesh\Core\Contracts;

/**
 * Interface for text generation responses.
 */
interface ResponseInterface
{
    /**
     * Get the generated text.
     *
     * @return string The complete text response
     */
    public function getText(): string;

    /**
     * Get usage information for the request.
     *
     * @return UsageInterface Token usage and cost information
     */
    public function getUsage(): UsageInterface;

    /**
     * Get the finish reason.
     *
     * @return string The reason generation stopped (e.g., 'stop', 'length', 'tool_calls')
     */
    public function getFinishReason(): string;

    /**
     * Get the raw provider response.
     *
     * @return array The unmodified response from the provider API
     */
    public function getRaw(): array;
}
