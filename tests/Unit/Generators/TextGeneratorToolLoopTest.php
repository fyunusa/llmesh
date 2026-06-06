<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Generators;

use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Contracts\ResponseInterface;
use LLMesh\Core\Data\ToolCall;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Core\Generators\TextGenerator;
use LLMesh\Core\Generators\TextResponse;
use LLMesh\Core\Generators\Usage;
use LLMesh\Core\Tools\Tool;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\Generators\TextGenerator
 */
final class TextGeneratorToolLoopTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a minimal OpenAI-shaped raw response with `finishReason = 'tool_calls'`
     * and one tool call embedded.
     *
     * @param ToolCall[] $toolCalls
     */
    private function makeToolCallRaw(array $toolCalls): array
    {
        $rawCalls = array_map(fn (ToolCall $tc) => [
            'id'       => $tc->id,
            'type'     => 'function',
            'function' => [
                'name'      => $tc->name,
                'arguments' => json_encode($tc->arguments, JSON_THROW_ON_ERROR),
            ],
        ], $toolCalls);

        return [
            'choices' => [[
                'message' => [
                    'role'       => 'assistant',
                    'content'    => null,
                    'tool_calls' => $rawCalls,
                ],
                'finish_reason' => 'tool_calls',
            ]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ];
    }

    /**
     * Build a regular text response (finishReason = 'stop').
     */
    private function makeTextRaw(string $text): array
    {
        return [
            'choices' => [[
                'message'       => ['role' => 'assistant', 'content' => $text],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
        ];
    }

    /**
     * Build a real TextResponse from a raw array.
     */
    private function makeResponse(array $raw): TextResponse
    {
        $finishReason = $raw['choices'][0]['finish_reason'] ?? 'stop';
        $text         = $raw['choices'][0]['message']['content'] ?? '';

        return new TextResponse(
            text: is_string($text) ? $text : '',
            usage: new Usage(
                inputTokens: $raw['usage']['prompt_tokens'] ?? 10,
                outputTokens: $raw['usage']['completion_tokens'] ?? 20,
            ),
            finishReason: $finishReason,
            raw: $raw,
        );
    }

    /**
     * Build a provider mock that returns the given responses in sequence.
     *
     * @param TextResponse[] $responses
     */
    private function makeProvider(array $responses, int $expectedCalls = -1): ProviderInterface
    {
        $provider = $this->createMock(ProviderInterface::class);

        if ($expectedCalls >= 0) {
            $provider->expects($this->exactly($expectedCalls))->method('chat')
                ->willReturnOnConsecutiveCalls(...$responses);
        } else {
            $provider->method('chat')
                ->willReturnOnConsecutiveCalls(...$responses);
        }

        return $provider;
    }

    private function weatherTool(): Tool
    {
        return Tool::make('get_weather')
            ->description('Get weather')
            ->parameters(['city' => Tool::string()->required()])
            ->handler(fn (array $p) => ['temperature' => 22, 'city' => $p['city']]);
    }

    // -------------------------------------------------------------------------
    // Two-step tool loop
    // -------------------------------------------------------------------------

    public function testProviderCalledTwiceForOneToolCall(): void
    {
        // Step 1: provider requests a tool call
        $step1Raw = $this->makeToolCallRaw([
            new ToolCall('tc-1', 'get_weather', ['city' => 'London']),
        ]);
        // Step 2: provider returns final text
        $step2Raw = $this->makeTextRaw('It is 22°C in London.');

        $provider = $this->makeProvider(
            [$this->makeResponse($step1Raw), $this->makeResponse($step2Raw)],
            expectedCalls: 2,
        );

        $options = GenerateTextOptions::make()
            ->withPrompt('What is the weather in London?')
            ->withTools([$this->weatherTool()]);

        $result = (new TextGenerator($provider))->generate($options);

        self::assertSame('It is 22°C in London.', $result->getText());
        self::assertSame('stop', $result->getFinishReason());
    }

    public function testToolResultIsAppendedBeforeSecondProviderCall(): void
    {
        $step1Raw = $this->makeToolCallRaw([
            new ToolCall('tc-1', 'get_weather', ['city' => 'Paris']),
        ]);
        $step2Raw = $this->makeTextRaw('Paris is 22°C.');

        $capturedMessages = null;

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('chat')
            ->willReturnCallback(function (
                array $messages,
                array $opts
            ) use (
                &$capturedMessages,
                $step1Raw,
                $step2Raw,
            ) {
                static $call = 0;
                $call++;
                if ($call === 1) {
                    return $this->makeResponse($step1Raw);
                }
                $capturedMessages = $messages;
                return $this->makeResponse($step2Raw);
            });

        $options = GenerateTextOptions::make()
            ->withPrompt('Paris weather?')
            ->withTools([$this->weatherTool()]);

        (new TextGenerator($provider))->generate($options);

        // After the second call the messages must include a tool role message
        self::assertNotNull($capturedMessages);
        $roles = array_map(fn ($m) => $m->role->value, $capturedMessages);
        self::assertContains('tool', $roles);
    }

    // -------------------------------------------------------------------------
    // maxSteps
    // -------------------------------------------------------------------------

    public function testMaxStepsStopsLoopEvenIfProviderKeepsRequestingTools(): void
    {
        // Provider always returns 'tool_calls' — loop must stop at maxSteps
        $toolCallRaw = $this->makeToolCallRaw([
            new ToolCall('tc-x', 'get_weather', ['city' => 'Tokyo']),
        ]);

        // With maxSteps=3, provider must be called at most 4 times
        // (initial call + 3 tool-use iterations)
        $maxSteps = 3;
        $responses = array_fill(0, $maxSteps + 1, $this->makeResponse($toolCallRaw));

        $callCount = 0;
        $provider  = $this->createStub(ProviderInterface::class);
        $provider->method('chat')->willReturnCallback(function () use (
            &$callCount,
            $toolCallRaw,
        ) {
            $callCount++;
            return $this->makeResponse($toolCallRaw);
        });

        $options = GenerateTextOptions::make()
            ->withPrompt('Weather?')
            ->withTools([$this->weatherTool()])
            ->withMaxSteps($maxSteps);

        (new TextGenerator($provider))->generate($options);

        // Total calls = 1 (initial) + maxSteps (iterations)
        self::assertSame($maxSteps + 1, $callCount);
    }

    public function testDefaultMaxStepsIsFive(): void
    {
        $opts = GenerateTextOptions::make();
        self::assertSame(5, $opts->maxSteps);
    }

    // -------------------------------------------------------------------------
    // onToolCall callback
    // -------------------------------------------------------------------------

    public function testOnToolCallCallbackFiredWithCorrectToolCall(): void
    {
        $capturedCalls = [];

        $step1Raw = $this->makeToolCallRaw([
            new ToolCall('tc-1', 'get_weather', ['city' => 'Berlin']),
        ]);
        $step2Raw = $this->makeTextRaw('Berlin weather data.');

        $provider = $this->makeProvider([
            $this->makeResponse($step1Raw),
            $this->makeResponse($step2Raw),
        ]);

        $options = GenerateTextOptions::make()
            ->withPrompt('Berlin weather?')
            ->withTools([$this->weatherTool()])
            ->onToolCall(function (ToolCall $tc) use (&$capturedCalls): void {
                $capturedCalls[] = $tc;
            });

        (new TextGenerator($provider))->generate($options);

        self::assertCount(1, $capturedCalls);
        self::assertSame('get_weather', $capturedCalls[0]->name);
        self::assertSame('Berlin', $capturedCalls[0]->arguments['city']);
    }

    public function testOnToolCallFiredForEachToolCallInParallelResponse(): void
    {
        $capturedCalls = [];

        $step1Raw = $this->makeToolCallRaw([
            new ToolCall('tc-1', 'get_weather', ['city' => 'Oslo']),
            new ToolCall('tc-2', 'get_weather', ['city' => 'Bergen']),
        ]);
        $step2Raw = $this->makeTextRaw('Done.');

        $provider = $this->makeProvider([
            $this->makeResponse($step1Raw),
            $this->makeResponse($step2Raw),
        ]);

        $options = GenerateTextOptions::make()
            ->withPrompt('Scandinavian weather?')
            ->withTools([$this->weatherTool()])
            ->onToolCall(function (ToolCall $tc) use (&$capturedCalls): void {
                $capturedCalls[] = $tc->name . ':' . $tc->arguments['city'];
            });

        (new TextGenerator($provider))->generate($options);

        self::assertCount(2, $capturedCalls);
        self::assertContains('get_weather:Oslo', $capturedCalls);
        self::assertContains('get_weather:Bergen', $capturedCalls);
    }

    // -------------------------------------------------------------------------
    // No tools — no looping
    // -------------------------------------------------------------------------

    public function testNoLoopWhenNoToolsConfigured(): void
    {
        // Even if provider returns tool_calls finish reason, no loop should happen
        $toolCallRaw = $this->makeToolCallRaw([
            new ToolCall('tc-1', 'get_weather', ['city' => 'X']),
        ]);

        $provider = $this->makeProvider(
            [$this->makeResponse($toolCallRaw)],
            expectedCalls: 1,
        );

        $options = GenerateTextOptions::make()->withPrompt('hi'); // no tools

        (new TextGenerator($provider))->generate($options);
    }

    // -------------------------------------------------------------------------
    // Tool handler errors do not break the loop
    // -------------------------------------------------------------------------

    public function testBrokenToolResultIsPassedBackToProvider(): void
    {
        $brokenTool = Tool::make('get_weather')
            ->parameters(['city' => Tool::string()->required()])
            ->handler(function (): never {
                throw new \RuntimeException('Service unavailable');
            });

        $step1Raw = $this->makeToolCallRaw([
            new ToolCall('tc-1', 'get_weather', ['city' => 'Rome']),
        ]);
        $step2Raw = $this->makeTextRaw('Sorry, could not get weather.');

        $capturedMessages = null;
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('chat')
            ->willReturnCallback(function (array $messages) use (
                &$capturedMessages,
                $step1Raw,
                $step2Raw,
            ) {
                static $call = 0;
                $call++;
                if ($call === 1) {
                    return $this->makeResponse($step1Raw);
                }
                $capturedMessages = $messages;
                return $this->makeResponse($step2Raw);
            });

        $options = GenerateTextOptions::make()
            ->withPrompt('Rome weather?')
            ->withTools([$brokenTool]);

        $result = (new TextGenerator($provider))->generate($options);

        // Provider still called twice
        self::assertNotNull($capturedMessages);
        // A tool message was appended (error result)
        $roles = array_map(fn ($m) => $m->role->value, $capturedMessages);
        self::assertContains('tool', $roles);
        self::assertSame('Sorry, could not get weather.', $result->getText());
    }
}
