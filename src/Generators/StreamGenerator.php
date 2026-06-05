<?php

declare(strict_types=1);

namespace LLMesh\Core\Generators;

use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Data\Message;

/**
 * Generates a streaming response using a provider.
 *
 * Mirrors TextGenerator but calls `$provider->stream()` instead of
 * `$provider->chat()` and returns a lazy StreamResponse rather than
 * a fully-resolved TextResponse.
 *
 * The underlying generator is never eagerly consumed — chunks are only
 * pulled from the provider as the caller iterates the StreamResponse.
 */
final class StreamGenerator
{
    /**
     * @param ProviderInterface $provider The LLM provider to stream from
     */
    public function __construct(
        private readonly ProviderInterface $provider,
    ) {
    }

    /**
     * Begin a streaming generation from the provider.
     *
     * @param GenerateTextOptions $options Generation options (prompt or messages required)
     *
     * @return StreamResponse A lazy stream of ChunkDelta objects
     *
     * @throws \RuntimeException        If the provider does not support streaming
     * @throws \LLMesh\Core\Exceptions\ValidationException If options are invalid
     */
    public function stream(GenerateTextOptions $options): StreamResponse
    {
        $options->validate();

        if (!$this->provider->supports('streaming')) {
            throw new \RuntimeException(
                sprintf(
                    'Provider "%s" does not support streaming. '
                    . 'Use generateText() instead, or choose a provider that supports streaming.',
                    get_class($this->provider),
                )
            );
        }

        $messages       = $this->buildMessages($options);
        $providerOptions = $this->buildProviderOptions($options);

        $streamInterface = $this->provider->stream($messages, $providerOptions);

        // Wrap the StreamInterface's generator in our StreamResponse so it
        // participates in the full LLMesh contract (toText, toSSE, pipe, etc.)
        return new StreamResponse($streamInterface->getChunks());
    }

    // -------------------------------------------------------------------------
    // Private helpers (mirrors TextGenerator logic)
    // -------------------------------------------------------------------------

    /**
     * Build the messages array for the provider call.
     *
     * If `$options->messages` is provided it takes precedence; otherwise the
     * single `$options->prompt` is wrapped in a user Message.
     *
     * @param GenerateTextOptions $options
     * @return array<int, Message>
     */
    private function buildMessages(GenerateTextOptions $options): array
    {
        if (!empty($options->messages)) {
            return $options->messages;
        }

        return [Message::user($options->prompt ?? '')];
    }

    /**
     * Build the provider-specific options array from GenerateTextOptions.
     *
     * @param GenerateTextOptions $options
     * @return array<string, mixed>
     */
    private function buildProviderOptions(GenerateTextOptions $options): array
    {
        $providerOptions = [];

        if ($options->system) {
            $providerOptions['system'] = $options->system;
        }

        if ($options->temperature !== null) {
            $providerOptions['temperature'] = $options->temperature;
        }

        if ($options->maxTokens !== null) {
            $providerOptions['max_tokens'] = $options->maxTokens;
        }

        if (!empty($options->stopSequences)) {
            $providerOptions['stop'] = $options->stopSequences;
        }

        if (!empty($options->tools)) {
            $providerOptions['tools'] = $options->tools;
        }

        return $providerOptions;
    }
}
