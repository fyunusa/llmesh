<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Config;

use LLMesh\Core\Config\LLMeshConfig;
use PHPUnit\Framework\TestCase;

final class LLMeshConfigTest extends TestCase
{
    public function testCanCreateConfigFromArray(): void
    {
        $config = LLMeshConfig::fromArray([
            'default_provider' => 'openai',
            'timeout' => 60,
        ]);

        $this->assertSame('openai', $config->get('default_provider'));
        $this->assertSame(60, $config->get('timeout'));
    }

    public function testDefaultValuesAreUsedWhenNotProvided(): void
    {
        $config = LLMeshConfig::fromArray([]);

        $this->assertSame(30, $config->get('timeout'));
        $this->assertSame(3, $config->get('retry_attempts'));
        $this->assertSame(500, $config->get('retry_delay_ms'));
        $this->assertFalse($config->get('log_requests'));
    }

    public function testCanGetConfigWithDefault(): void
    {
        $config = LLMeshConfig::fromArray([]);

        $this->assertSame('default_value', $config->get('nonexistent', 'default_value'));
    }

    public function testConvenienceGettersWork(): void
    {
        $config = LLMeshConfig::fromArray([
            'default_provider' => 'openai',
            'timeout' => 45,
            'retry_attempts' => 5,
            'retry_delay_ms' => 1000,
            'log_requests' => true,
        ]);

        $this->assertSame('openai', $config->getDefaultProvider());
        $this->assertSame(45, $config->getTimeout());
        $this->assertSame(5, $config->getRetryAttempts());
        $this->assertSame(1000, $config->getRetryDelayMs());
        $this->assertTrue($config->shouldLogRequests());
    }

    public function testCanPartiallyOverrideDefaults(): void
    {
        $config = LLMeshConfig::fromArray([
            'timeout' => 90,
        ]);

        $this->assertSame(90, $config->get('timeout'));
        $this->assertSame(3, $config->get('retry_attempts')); // default
    }
}
