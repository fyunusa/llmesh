<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Generators;

use LLMesh\Core\Generators\Usage;
use PHPUnit\Framework\TestCase;

final class UsageTest extends TestCase
{
    public function testCanConstructUsage(): void
    {
        $usage = new Usage(
            inputTokens: 100,
            outputTokens: 50,
            totalTokens: 150,
            estimatedCost: 0.25,
        );

        $this->assertSame(100, $usage->getInputTokens());
        $this->assertSame(50, $usage->getOutputTokens());
        $this->assertSame(150, $usage->getTotalTokens());
        $this->assertSame(0.25, $usage->getEstimatedCost());
    }

    public function testCanCreateUsageFromArray(): void
    {
        $data = [
            'input_tokens' => 100,
            'output_tokens' => 50,
            'total_tokens' => 150,
            'estimated_cost' => 0.25,
        ];

        $usage = Usage::fromArray($data);

        $this->assertSame(100, $usage->getInputTokens());
        $this->assertSame(50, $usage->getOutputTokens());
        $this->assertSame(150, $usage->getTotalTokens());
        $this->assertSame(0.25, $usage->getEstimatedCost());
    }

    public function testCalculatesTotalTokensAutomatically(): void
    {
        $usage = Usage::fromArray([
            'input_tokens' => 100,
            'output_tokens' => 50,
        ]);

        $this->assertSame(150, $usage->getTotalTokens());
    }

    public function testPropertiesAreReadonly(): void
    {
        $usage = new Usage(inputTokens: 100, outputTokens: 50);

        $this->expectException(\Error::class);
        $usage->inputTokens = 200;
    }

    public function testCanConvertToArray(): void
    {
        $usage = new Usage(
            inputTokens: 100,
            outputTokens: 50,
            totalTokens: 150,
            estimatedCost: 0.25,
        );

        $array = $usage->toArray();

        $this->assertSame(100, $array['input_tokens']);
        $this->assertSame(50, $array['output_tokens']);
        $this->assertSame(150, $array['total_tokens']);
        $this->assertSame(0.25, $array['estimated_cost']);
    }

    public function testEstimatedCostCanBeNull(): void
    {
        $usage = new Usage(inputTokens: 100, outputTokens: 50);

        $this->assertNull($usage->getEstimatedCost());
    }
}
