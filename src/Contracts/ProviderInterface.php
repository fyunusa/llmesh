<?php

declare(strict_types=1);

namespace LLMesh\Core\Contracts;

/**
 * Interface for LLM providers.
 *
 * Implementations should handle communication with specific LLM APIs
 * (OpenAI, Anthropic, etc.) and provide a unified interface for the library.
 */
interface ProviderInterface
{
    /**
     * Generate text based on messages.
     *
     * @param array $messages Array of Message DTOs or associative arrays with 'role' and 'content'
     * @param array $options Provider-specific options (temperature, max_tokens, etc.)
     *
     * @return ResponseInterface The response from the provider
     *
     * @throws \LLMesh\Core\Exceptions\ProviderException On provider-specific errors
     * @throws \LLMesh\Core\Exceptions\RateLimitException When rate limited
     * @throws \LLMesh\Core\Exceptions\TokenLimitException When token limit exceeded
     */
    public function chat(array $messages, array $options = []): ResponseInterface;

    /**
     * Stream text generation based on messages.
     *
     * @param array $messages Array of Message DTOs or associative arrays with 'role' and 'content'
     * @param array $options Provider-specific options (temperature, max_tokens, etc.)
     *
     * @return StreamInterface A stream of ChunkDelta objects
     *
     * @throws \LLMesh\Core\Exceptions\ProviderException On provider-specific errors
     * @throws \LLMesh\Core\Exceptions\RateLimitException When rate limited
     * @throws \LLMesh\Core\Exceptions\TokenLimitException When token limit exceeded
     */
    public function stream(array $messages, array $options = []): StreamInterface;

    /**
     * Generate embeddings for input text.
     *
     * @param string|array $input Text to embed, or array of texts
     * @param array $options Provider-specific options
     *
     * @return EmbeddingResponseInterface The embedding response
     *
     * @throws \LLMesh\Core\Exceptions\ProviderException On provider-specific errors
     * @throws \LLMesh\Core\Exceptions\RateLimitException When rate limited
     */
    public function embed(string|array $input, array $options = []): EmbeddingResponseInterface;

    /**
     * Batch-embed multiple inputs in a single call or sequentially.
     *
     * @param string[] $inputs  Array of texts to embed
     * @param array    $options Provider-specific options
     *
     * @return EmbeddingResponseInterface[] An array of embedding responses
     */
    public function embedBatch(array $inputs, array $options = []): array;

    /**
     * Check if provider supports a specific capability.
     *
     * @param string $capability One of: 'streaming', 'tools', 'embeddings'
     *
     * @return bool True if capability is supported, false otherwise
     */
    public function supports(string $capability): bool;
}
