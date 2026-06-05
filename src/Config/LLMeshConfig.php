<?php

declare(strict_types=1);

namespace LLMesh\Core\Config;

/**
 * Global LLMesh configuration.
 */
final class LLMeshConfig
{
    /**
     * Default configuration values.
     *
     * @var array<string, mixed>
     */
    private static array $defaults = [
        'default_provider' => null,
        'timeout' => 30,
        'retry_attempts' => 3,
        'retry_delay_ms' => 500,
        'log_requests' => false,
    ];

    /**
     * Configuration storage.
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Constructor.
     *
     * @param array<string, mixed> $config Configuration array
     */
    private function __construct(array $config = [])
    {
        $this->config = array_merge(self::$defaults, $config);
    }

    /**
     * Create configuration from array.
     *
     * @param array<string, mixed> $config Configuration array
     *
     * @return self
     */
    public static function fromArray(array $config): self
    {
        return new self($config);
    }

    /**
     * Get a configuration value.
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }

        return $default ?? self::$defaults[$key] ?? null;
    }

    /**
     * Get default provider name.
     *
     * @return string|null
     */
    public function getDefaultProvider(): ?string
    {
        return $this->get('default_provider');
    }

    /**
     * Get timeout in seconds.
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return (int) $this->get('timeout');
    }

    /**
     * Get retry attempts.
     *
     * @return int
     */
    public function getRetryAttempts(): int
    {
        return (int) $this->get('retry_attempts');
    }

    /**
     * Get retry delay in milliseconds.
     *
     * @return int
     */
    public function getRetryDelayMs(): int
    {
        return (int) $this->get('retry_delay_ms');
    }

    /**
     * Check if request logging is enabled.
     *
     * @return bool
     */
    public function shouldLogRequests(): bool
    {
        return (bool) $this->get('log_requests');
    }
}
