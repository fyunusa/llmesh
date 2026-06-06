<?php

declare(strict_types=1);

namespace LLMesh\Core\Config;

/**
 * Per-provider configuration.
 */
final class ProviderConfig
{
    /**
     * Constructor.
     *
     * @param string $apiKey API key for the provider
     * @param string|null $baseUrl Base URL for provider API
     * @param string|null $model Model name to use
     * @param int|null $maxTokens Maximum tokens for responses
     * @param float|null $temperature Temperature for generation
     * @param array $options Additional provider-specific options
     */
    public function __construct(
        public readonly string $apiKey,
        public readonly ?string $baseUrl = null,
        public readonly ?string $model = null,
        public readonly ?int $maxTokens = null,
        public readonly ?float $temperature = null,
        public readonly array $options = [],
    ) {
    }

    /**
     * Create configuration from array.
     *
     * @param array $config Configuration array
     *
     * @return self
     *
     * @throws \LLMesh\Core\Exceptions\ValidationException If api_key is missing
     */
    public static function fromArray(array $config): self
    {
        if (empty($config['api_key'])) {
            throw new \LLMesh\Core\Exceptions\ValidationException(
                'Provider configuration requires an api_key',
                ['api_key' => 'API key is required'],
            );
        }

        return new self(
            apiKey: $config['api_key'],
            baseUrl: $config['base_url'] ?? null,
            model: $config['model'] ?? null,
            maxTokens: isset($config['max_tokens']) ? (int) $config['max_tokens'] : null,
            temperature: isset($config['temperature']) ? (float) $config['temperature'] : null,
            options: $config['options'] ?? [],
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'api_key' => $this->apiKey,
            'base_url' => $this->baseUrl,
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'options' => $this->options,
        ];
    }
}
