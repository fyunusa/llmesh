<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Config;

use LLMesh\Core\Config\ProviderConfig;
use LLMesh\Core\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class ProviderConfigTest extends TestCase
{
    public function testCanConstructProviderConfig(): void
    {
        $config = new ProviderConfig(
            apiKey: 'sk-1234567890',
            baseUrl: 'https://api.example.com',
            model: 'gpt-4o',
            maxTokens: 2000,
            temperature: 0.7,
        );

        $this->assertSame('sk-1234567890', $config->apiKey);
        $this->assertSame('https://api.example.com', $config->baseUrl);
        $this->assertSame('gpt-4o', $config->model);
        $this->assertSame(2000, $config->maxTokens);
        $this->assertSame(0.7, $config->temperature);
    }

    public function testCanCreateProviderConfigFromArray(): void
    {
        $config = ProviderConfig::fromArray([
            'api_key' => 'key123',
            'model' => 'claude-3',
            'max_tokens' => 4096,
            'temperature' => 0.5,
        ]);

        $this->assertSame('key123', $config->apiKey);
        $this->assertSame('claude-3', $config->model);
        $this->assertSame(4096, $config->maxTokens);
        $this->assertSame(0.5, $config->temperature);
    }

    public function testThrowsExceptionWhenApiKeyMissing(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('api_key');

        ProviderConfig::fromArray([
            'model' => 'gpt-4o',
        ]);
    }

    public function testPropertiesAreReadonly(): void
    {
        $config = new ProviderConfig('key');

        $this->expectException(\Error::class);
        $config->apiKey = 'modified';
    }

    public function testCanConvertToArray(): void
    {
        $config = new ProviderConfig(
            apiKey: 'test-key',
            model: 'test-model',
            maxTokens: 1000,
            temperature: 0.8,
        );

        $array = $config->toArray();

        $this->assertSame('test-key', $array['api_key']);
        $this->assertSame('test-model', $array['model']);
        $this->assertSame(1000, $array['max_tokens']);
        $this->assertSame(0.8, $array['temperature']);
    }

    public function testSupportsAdditionalOptions(): void
    {
        $options = ['custom_param' => 'value'];
        $config = new ProviderConfig(
            apiKey: 'key',
            options: $options,
        );

        $this->assertSame($options, $config->options);
    }
}
