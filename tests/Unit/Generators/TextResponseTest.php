<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Generators;

use LLMesh\Core\Generators\TextResponse;
use LLMesh\Core\Generators\Usage;
use PHPUnit\Framework\TestCase;

final class TextResponseTest extends TestCase
{
    public function testCanConstructTextResponse(): void
    {
        $usage = new Usage(inputTokens: 100, outputTokens: 50);
        $response = new TextResponse(
            text: 'Hello, world!',
            usage: $usage,
            finishReason: 'stop',
            raw: ['data' => 'raw'],
        );

        $this->assertSame('Hello, world!', $response->getText());
        $this->assertSame($usage, $response->getUsage());
        $this->assertSame('stop', $response->getFinishReason());
        $this->assertSame(['data' => 'raw'], $response->getRaw());
    }

    public function testCanCreateFromProviderResponse(): void
    {
        $raw = [
            'choices' => [
                [
                    'message' => ['content' => 'Generated text'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
            ],
        ];

        $response = TextResponse::fromProviderResponse($raw, function ($data) {
            return [
                'text' => $data['choices'][0]['message']['content'],
                'usage' => [
                    'input_tokens' => $data['usage']['prompt_tokens'],
                    'output_tokens' => $data['usage']['completion_tokens'],
                ],
                'finishReason' => $data['choices'][0]['finish_reason'],
            ];
        });

        $this->assertSame('Generated text', $response->getText());
        $this->assertSame('stop', $response->getFinishReason());
        $this->assertSame(10, $response->getUsage()->getInputTokens());
        $this->assertSame(20, $response->getUsage()->getOutputTokens());
        $this->assertSame($raw, $response->getRaw());
    }

    public function testPropertiesAreReadonly(): void
    {
        $usage = new Usage(inputTokens: 100, outputTokens: 50);
        $response = new TextResponse(
            text: 'Hello',
            usage: $usage,
            finishReason: 'stop',
            raw: [],
        );

        $this->expectException(\Error::class);
        $response->text = 'Modified';
    }

    public function testCanConvertToArray(): void
    {
        $usage = new Usage(inputTokens: 100, outputTokens: 50, totalTokens: 150);
        $response = new TextResponse(
            text: 'Hello, world!',
            usage: $usage,
            finishReason: 'stop',
            raw: ['data' => 'raw'],
        );

        $array = $response->toArray();

        $this->assertSame('Hello, world!', $array['text']);
        $this->assertSame('stop', $array['finish_reason']);
        $this->assertIsArray($array['usage']);
        $this->assertSame('raw', $array['raw']['data']);
    }

    public function testFinishReasonCanBeToolCalls(): void
    {
        $usage = new Usage(inputTokens: 100, outputTokens: 50);
        $response = new TextResponse(
            text: '',
            usage: $usage,
            finishReason: 'tool_calls',
            raw: [],
        );

        $this->assertSame('tool_calls', $response->getFinishReason());
    }
}
