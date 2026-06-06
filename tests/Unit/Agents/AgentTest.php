<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Agents;

use LLMesh\Core\Agents\Agent;
use LLMesh\Core\Agents\AgentResult;
use LLMesh\Core\Agents\AgentStep;
use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Data\ToolCall;
use LLMesh\Core\Events\AgentFailed;
use LLMesh\Core\Events\AgentFinished;
use LLMesh\Core\Events\AgentStarted;
use LLMesh\Core\Events\AgentStepCompleted;
use LLMesh\Core\Events\AgentToolCalled;
use LLMesh\Core\Generators\TextResponse;
use LLMesh\Core\Generators\Usage;
use LLMesh\Core\Tools\Tool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \LLMesh\Core\Agents\Agent
 * @covers \LLMesh\Core\Agents\AgentResult
 * @covers \LLMesh\Core\Agents\AgentStep
 */
final class AgentTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a TextResponse with a text finish (no tool calls).
     */
    private function textResponse(string $text, int $input = 10, int $output = 20): TextResponse
    {
        return new TextResponse(
            text:         $text,
            usage:        new Usage($input, $output),
            finishReason: 'stop',
            raw:          [],
        );
    }

    /**
     * Build a TextResponse that signals tool_calls, with an embedded OpenAI-style
     * tool call for the given tool name.
     */
    private function toolCallResponse(string $toolName, array $args = []): TextResponse
    {
        $raw = [
            'choices' => [[
                'message' => [
                    'tool_calls' => [[
                        'id'       => 'call-' . $toolName,
                        'function' => [
                            'name'      => $toolName,
                            'arguments' => json_encode($args),
                        ],
                    ]],
                ],
                'finish_reason' => 'tool_calls',
            ]],
        ];

        return new TextResponse(
            text:         '',
            usage:        new Usage(15, 5),
            finishReason: 'tool_calls',
            raw:          $raw,
        );
    }

    /**
     * Build a simple echo tool that returns its arguments as-is.
     */
    private function echoTool(string $name): Tool
    {
        return Tool::make($name)
            ->description("Echo tool: $name")
            ->handler(fn (array $p) => ['echoed' => $p]);
    }

    // -------------------------------------------------------------------------
    // 1. Agent completes in 1 step (no tools needed)
    // -------------------------------------------------------------------------

    public function testSingleStepNoTools(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('chat')
            ->willReturn($this->textResponse('Final answer'));

        $agent  = Agent::make($provider, 'You are helpful.', []);
        $result = $agent->run('Hello');

        $this->assertSame('Final answer', $result->finalText);
        $this->assertSame(1, $result->totalSteps);
        $this->assertFalse($result->stoppedEarly);
        $this->assertCount(1, $result->steps);
    }

    // -------------------------------------------------------------------------
    // 2. Agent uses a tool then answers: 2 provider calls
    // -------------------------------------------------------------------------

    public function testToolThenFinalAnswer(): void
    {
        $callCount = 0;
        $provider  = $this->createMock(ProviderInterface::class);
        $provider->expects($this->exactly(2))
            ->method('chat')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return $this->toolCallResponse('get_weather', ['city' => 'London']);
                }
                return $this->textResponse('It is sunny!');
            });

        $weatherTool = $this->echoTool('get_weather');
        $agent       = Agent::make($provider, 'Weather assistant', [$weatherTool]);

        $result = $agent->run('What is the weather in London?');

        $this->assertSame('It is sunny!', $result->finalText);
        $this->assertSame(2, $result->totalSteps);
        $this->assertFalse($result->stoppedEarly);

        // Step 1 should have recorded the tool call
        $step1 = $result->steps[0];
        $this->assertCount(1, $step1->toolCalls);
        $this->assertSame('get_weather', $step1->toolCalls[0]->name);
        $this->assertCount(1, $step1->toolResults);
    }

    // -------------------------------------------------------------------------
    // 3. maxSteps enforcement
    // -------------------------------------------------------------------------

    public function testMaxStepsEnforcesHardCeiling(): void
    {
        // Provider always returns tool_calls → loop should stop at maxSteps
        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->exactly(3))   // maxSteps = 3
            ->method('chat')
            ->willReturn($this->toolCallResponse('looping_tool'));

        $loopingTool = $this->echoTool('looping_tool');
        $agent       = Agent::make($provider, 'Test', [$loopingTool], maxSteps: 3);

        $result = $agent->run('Go forever');

        $this->assertTrue($result->stoppedEarly);
        $this->assertSame(3, $result->totalSteps);
    }

    // -------------------------------------------------------------------------
    // 4. onStep callback fired for each step
    // -------------------------------------------------------------------------

    public function testOnStepCallbackFiredForEachStep(): void
    {
        $callCount = 0;
        $provider  = $this->createMock(ProviderInterface::class);
        $provider->method('chat')
            ->willReturnOnConsecutiveCalls(
                $this->toolCallResponse('some_tool'),
                $this->textResponse('Done'),
            );

        $capturedSteps = [];
        $agent = Agent::make($provider, 'Test', [$this->echoTool('some_tool')])
            ->onStep(function (AgentStep $step) use (&$capturedSteps) {
                $capturedSteps[] = $step;
            });

        $agent->run('Go');

        $this->assertCount(2, $capturedSteps);
        $this->assertSame(1, $capturedSteps[0]->stepNumber);
        $this->assertSame(2, $capturedSteps[1]->stepNumber);
    }

    // -------------------------------------------------------------------------
    // 5. Aggregated usage sums tokens across all steps
    // -------------------------------------------------------------------------

    public function testAggregatedUsageSumsTokensAcrossAllSteps(): void
    {
        // Step 1: tool call → 15 input + 5 output
        // Step 2: final    → 10 input + 20 output
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('chat')
            ->willReturnOnConsecutiveCalls(
                $this->toolCallResponse('calc'),   // Usage(15, 5)
                $this->textResponse('Result', input: 10, output: 20),
            );

        $agent  = Agent::make($provider, 'Math', [$this->echoTool('calc')]);
        $result = $agent->run('Calculate something');

        $this->assertSame(25, $result->usage->getInputTokens());   // 15+10
        $this->assertSame(25, $result->usage->getOutputTokens());  // 5+20
        $this->assertSame(50, $result->usage->getTotalTokens());   // 25+25
    }

    // -------------------------------------------------------------------------
    // 6. AgentFailed event dispatched when provider throws
    // -------------------------------------------------------------------------

    public function testAgentFailedEventDispatchedOnProviderException(): void
    {
        $dispatchedEvents = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('chat')
            ->willThrowException(new \RuntimeException('API error'));

        $agent = Agent::make($provider, 'Test')
            ->withEventDispatcher($dispatcher);

        try {
            $agent->run('Hello');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('API error', $e->getMessage());
        }

        // Verify AgentFailed was dispatched
        $failedEvents = array_filter($dispatchedEvents, fn ($e) => $e instanceof AgentFailed);
        $this->assertCount(1, $failedEvents);

        $failedEvent = array_values($failedEvents)[0];
        $this->assertInstanceOf(\RuntimeException::class, $failedEvent->exception);
        $this->assertSame('API error', $failedEvent->exception->getMessage());
    }

    // -------------------------------------------------------------------------
    // 7. Full PSR-14 event lifecycle (no tools)
    // -------------------------------------------------------------------------

    public function testPsr14EventLifecycleWithoutTools(): void
    {
        $dispatchedEvents = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('chat')->willReturn($this->textResponse('Hello'));

        $agent = Agent::make($provider, 'Test')
            ->withEventDispatcher($dispatcher);

        $agent->run('Ping');

        $classes = array_map('get_class', $dispatchedEvents);

        $this->assertContains(AgentStarted::class, $classes);
        $this->assertContains(AgentStepCompleted::class, $classes);
        $this->assertContains(AgentFinished::class, $classes);
    }

    // -------------------------------------------------------------------------
    // 8. AgentToolCalled event dispatched for each tool in the step
    // -------------------------------------------------------------------------

    public function testAgentToolCalledEventDispatchedPerTool(): void
    {
        $dispatchedEvents = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('chat')
            ->willReturnOnConsecutiveCalls(
                $this->toolCallResponse('my_tool'),
                $this->textResponse('Done'),
            );

        $agent = Agent::make($provider, 'Test', [$this->echoTool('my_tool')])
            ->withEventDispatcher($dispatcher);

        $agent->run('Go');

        $toolCalledEvents = array_values(
            array_filter($dispatchedEvents, fn ($e) => $e instanceof AgentToolCalled)
        );

        $this->assertCount(1, $toolCalledEvents);
        $this->assertSame('my_tool', $toolCalledEvents[0]->toolCall->name);
        $this->assertSame(1, $toolCalledEvents[0]->stepNumber);
    }

    // -------------------------------------------------------------------------
    // 9. AgentResult::toArray() is fully serializable
    // -------------------------------------------------------------------------

    public function testAgentResultToArrayIsFullySerializable(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('chat')->willReturn($this->textResponse('Answer'));

        $agent  = Agent::make($provider, 'Test');
        $result = $agent->run('Question');

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('final_text', $array);
        $this->assertArrayHasKey('total_steps', $array);
        $this->assertArrayHasKey('stopped_early', $array);
        $this->assertArrayHasKey('usage', $array);
        $this->assertArrayHasKey('steps', $array);

        // Must be JSON-encodable without errors
        $json = json_encode($array, JSON_THROW_ON_ERROR);
        $this->assertIsString($json);
    }

    // -------------------------------------------------------------------------
    // 10. withMemory: user message persisted, assistant reply persisted
    // -------------------------------------------------------------------------

    public function testMemoryIntegration(): void
    {
        $store = new \LLMesh\Core\Memory\InMemoryStore();

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('chat')->willReturn($this->textResponse('Hi there'));

        $agent = Agent::make($provider, 'Test')
            ->withMemory($store, 'sess-001');

        $agent->run('Hello');

        $history = $store->get('sess-001');
        // Should have: user message + assistant reply
        $this->assertCount(2, $history);
        $this->assertSame('user', $history[0]['role']);
        $this->assertSame('Hello', $history[0]['content']);
        $this->assertSame('assistant', $history[1]['role']);
        $this->assertSame('Hi there', $history[1]['content']);
    }

    // -------------------------------------------------------------------------
    // 11. AgentResult::getStepCount() and getTotalCost()
    // -------------------------------------------------------------------------

    public function testAgentResultHelpers(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('chat')->willReturn(new TextResponse(
            text:         'Done',
            usage:        new Usage(10, 20, estimatedCost: 0.005),
            finishReason: 'stop',
            raw:          [],
        ));

        $agent  = Agent::make($provider, 'Test');
        $result = $agent->run('Go');

        $this->assertSame(1, $result->getStepCount());
        $this->assertEqualsWithDelta(0.005, $result->getTotalCost(), 0.0001);
    }

    // -------------------------------------------------------------------------
    // 12. Immutability — builder methods return new instances
    // -------------------------------------------------------------------------

    public function testBuilderMethodsReturnNewInstances(): void
    {
        $provider = $this->createMock(ProviderInterface::class);

        $agent1 = Agent::make($provider, 'Base');
        $agent2 = $agent1->onStep(fn (AgentStep $s) => null);

        $this->assertNotSame($agent1, $agent2);
    }
}
