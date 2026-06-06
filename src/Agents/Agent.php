<?php

declare(strict_types=1);

namespace LLMesh\Core\Agents;

use LLMesh\Core\Contracts\MemoryStoreInterface;
use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Data\Message;
use LLMesh\Core\Data\ToolCall;
use LLMesh\Core\Events\AgentFailed;
use LLMesh\Core\Events\AgentFinished;
use LLMesh\Core\Events\AgentStarted;
use LLMesh\Core\Events\AgentStepCompleted;
use LLMesh\Core\Events\AgentToolCalled;
use LLMesh\Core\Tools\Tool;
use LLMesh\Core\Tools\ToolCallExtractor;
use LLMesh\Core\Tools\ToolExecutor;
use LLMesh\Core\Tools\ToolResult;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * A multi-step agentic loop built on top of `ProviderInterface`.
 *
 * The agent repeatedly calls the provider, executes any requested tool calls,
 * appends the results back to the conversation, and continues until either:
 *  - The provider returns a final (non-tool-call) response, or
 *  - The `maxSteps` ceiling is hit (returns with `stoppedEarly: true`).
 *
 * Usage:
 * ```php
 * $agent = Agent::make(
 *     provider:     $provider,
 *     systemPrompt: 'You are a backend engineer assistant.',
 *     tools:        [$searchTool, $codeReviewTool],
 *     maxSteps:     10,
 * );
 *
 * $result = $agent->run('Analyze our API and suggest improvements.');
 * echo $result->finalText;
 * ```
 *
 * Immutable after construction — all `with*` / `on*` methods return a new
 * instance so the agent can be safely reused across requests.
 */
final class Agent
{
    /**
     * @param ProviderInterface              $provider         The LLM provider to call
     * @param string                         $systemPrompt     System-level instructions given to the model
     * @param Tool[]                         $tools            Tools the model may call
     * @param int                            $maxSteps         Hard ceiling on provider calls (default 10)
     * @param MemoryStoreInterface|null      $memory           Optional persistent conversation history
     * @param string|null                    $sessionId        Session ID used with `$memory`
     * @param EventDispatcherInterface|null  $eventDispatcher  PSR-14 dispatcher for lifecycle events
     * @param \Closure|null                  $onStep           Callback invoked after each step with `AgentStep`
     */
    private function __construct(
        private readonly ProviderInterface $provider,
        private readonly string $systemPrompt,
        private readonly array $tools,
        private readonly int $maxSteps,
        private readonly ?MemoryStoreInterface $memory,
        private readonly ?string $sessionId,
        private readonly ?EventDispatcherInterface $eventDispatcher,
        private readonly ?\Closure $onStep,
    ) {
    }

    // -------------------------------------------------------------------------
    // Factory / builder
    // -------------------------------------------------------------------------

    /**
     * Create an `Agent` with the required dependencies.
     *
     * @param ProviderInterface $provider     The LLM provider
     * @param string            $systemPrompt System-level instructions
     * @param Tool[]            $tools        Tools available to the model
     * @param int               $maxSteps     Maximum provider calls before stopping (default 10)
     */
    public static function make(
        ProviderInterface $provider,
        string $systemPrompt,
        array $tools = [],
        int $maxSteps = 10,
    ): self {
        return new self(
            provider:        $provider,
            systemPrompt:    $systemPrompt,
            tools:           $tools,
            maxSteps:        $maxSteps,
            memory:          null,
            sessionId:       null,
            eventDispatcher: null,
            onStep:          null,
        );
    }

    /**
     * Attach persistent conversation memory.
     *
     * @param MemoryStoreInterface $store     The backing memory store
     * @param string               $sessionId Unique conversation session identifier
     */
    public function withMemory(MemoryStoreInterface $store, string $sessionId): self
    {
        return new self(
            provider:        $this->provider,
            systemPrompt:    $this->systemPrompt,
            tools:           $this->tools,
            maxSteps:        $this->maxSteps,
            memory:          $store,
            sessionId:       $sessionId,
            eventDispatcher: $this->eventDispatcher,
            onStep:          $this->onStep,
        );
    }

    /**
     * Attach a PSR-14 event dispatcher for lifecycle events.
     *
     * @param EventDispatcherInterface $dispatcher The event dispatcher
     */
    public function withEventDispatcher(EventDispatcherInterface $dispatcher): self
    {
        return new self(
            provider:        $this->provider,
            systemPrompt:    $this->systemPrompt,
            tools:           $this->tools,
            maxSteps:        $this->maxSteps,
            memory:          $this->memory,
            sessionId:       $this->sessionId,
            eventDispatcher: $dispatcher,
            onStep:          $this->onStep,
        );
    }

    /**
     * Register a callback invoked after each completed step.
     *
     * The callback receives the `AgentStep` DTO for that iteration.
     *
     * @param \Closure(AgentStep): void $callback
     */
    public function onStep(\Closure $callback): self
    {
        return new self(
            provider:        $this->provider,
            systemPrompt:    $this->systemPrompt,
            tools:           $this->tools,
            maxSteps:        $this->maxSteps,
            memory:          $this->memory,
            sessionId:       $this->sessionId,
            eventDispatcher: $this->eventDispatcher,
            onStep:          $callback,
        );
    }

    // -------------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------------

    /**
     * Run the agent loop for the given user prompt.
     *
     * The loop:
     * 1. Builds the initial message list (system + memory history + user)
     * 2. Checks the step ceiling **before** each provider call
     * 3. Calls the provider
     * 4. If the response contains tool calls: executes them, appends results, continues
     * 5. If the response is a final answer: builds and returns `AgentResult`
     *
     * @param string $prompt The user's input prompt
     * @return AgentResult The complete result, including all intermediate steps
     *
     * @throws \Throwable Any exception from the provider is re-thrown after dispatching `AgentFailed`
     */
    public function run(string $prompt): AgentResult
    {
        $toolNames = array_map(fn (Tool $t) => $t->getName(), $this->tools);

        $this->dispatch(new AgentStarted(
            provider:     $this->provider,
            systemPrompt: $this->systemPrompt,
            toolNames:    $toolNames,
            maxSteps:     $this->maxSteps,
        ));

        /** @var AgentStep[] $steps */
        $steps    = [];
        $messages = $this->buildInitialMessages($prompt);

        // Build provider options: system prompt + tool definitions
        $providerOptions = [];
        if ($this->systemPrompt !== '') {
            $providerOptions['system'] = $this->systemPrompt;
        }

        $toolDefs = array_map(fn (Tool $t) => $t->toArray(), $this->tools);
        if (!empty($toolDefs)) {
            $providerOptions['tools'] = $toolDefs;
        }

        $executor = new ToolExecutor();

        try {
            while (true) {
                // ── Hard ceiling check BEFORE the provider call ───────────
                if (count($steps) >= $this->maxSteps) {
                    $result = AgentResult::fromSteps(
                        finalText:    $steps[count($steps) - 1]->output->getText(),
                        steps:        $steps,
                        stoppedEarly: true,
                    );

                    $this->dispatch(new AgentFinished($result));
                    return $result;
                }

                // ── Provider call ─────────────────────────────────────────
                $stepStart = $this->nowMs();
                $response  = $this->provider->chat($messages, $providerOptions);
                $durationMs = $this->nowMs() - $stepStart;

                $stepNumber = count($steps) + 1;

                // ── Determine if this step has tool calls ─────────────────
                $toolCalls   = [];
                $toolResults = [];

                if ($response->getFinishReason() === 'tool_calls') {
                    $toolCalls = ToolCallExtractor::extract($response->getRaw());

                    foreach ($toolCalls as $toolCall) {
                        $toolResult    = $executor->execute($toolCall, $this->tools);
                        $toolResults[] = $toolResult;

                        $this->dispatch(new AgentToolCalled(
                            toolCall:   $toolCall,
                            toolResult: $toolResult,
                            stepNumber: $stepNumber,
                        ));
                    }
                }

                // ── Record step ───────────────────────────────────────────
                $step = new AgentStep(
                    stepNumber:  $stepNumber,
                    input:       $messages,
                    output:      $response,
                    toolCalls:   $toolCalls,
                    toolResults: $toolResults,
                    durationMs:  $durationMs,
                );

                $steps[] = $step;

                $this->dispatch(new AgentStepCompleted($step));

                if ($this->onStep !== null) {
                    ($this->onStep)($step);
                }

                // ── Terminal condition: no tool calls → final answer ───────
                if ($response->getFinishReason() !== 'tool_calls' || empty($toolCalls)) {
                    break;
                }

                // ── Extend conversation with assistant message + tool results
                $messages = $this->appendToolRound(
                    messages:    $messages,
                    response:    $response,
                    toolCalls:   $toolCalls,
                    toolResults: $toolResults,
                );
            }

            // ── Persist assistant reply to memory if configured ───────────
            if ($this->memory !== null && $this->sessionId !== null) {
                $lastStep = $steps[count($steps) - 1];
                $this->memory->append(
                    $this->sessionId,
                    Message::assistant($lastStep->output->getText())->toArray(),
                );
            }

            $result = AgentResult::fromSteps(
                finalText:    $steps[count($steps) - 1]->output->getText(),
                steps:        $steps,
                stoppedEarly: false,
            );

            $this->dispatch(new AgentFinished($result));
            return $result;
        } catch (\Throwable $e) {
            $this->dispatch(new AgentFailed(
                exception:     $e,
                stepsComplete: $steps,
            ));

            throw $e; // always rethrow — never swallow silently
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build the initial message list:
     *   [history from memory…] + [user message]
     *
     * The system prompt is passed as a provider option, not as a message, to
     * ensure both OpenAI and Anthropic providers handle it correctly.
     */
    private function buildInitialMessages(string $prompt): array
    {
        $messages = [];

        // Load stored conversation history
        if ($this->memory !== null && $this->sessionId !== null) {
            $history = $this->memory->get($this->sessionId);
            foreach ($history as $item) {
                if ($item instanceof Message) {
                    $messages[] = $item;
                } else {
                    $messages[] = new Message(
                        role: \LLMesh\Core\Data\MessageRole::from($item['role']),
                        content: $item['content'],
                        toolCallId: $item['toolCallId'] ?? null,
                        toolName:   $item['toolName'] ?? null,
                    );
                }
            }
        }

        // Append current user turn and persist it
        $userMessage = Message::user($prompt);
        $messages[]  = $userMessage;

        if ($this->memory !== null && $this->sessionId !== null) {
            $this->memory->append($this->sessionId, $userMessage->toArray());
        }

        return $messages;
    }

    /**
     * Extend the current messages array with:
     * 1. The assistant message (carrying the tool-call request)
     * 2. One tool-result message per executed tool
     *
     * @param  Message[]     $messages
     * @param  ToolCall[]    $toolCalls
     * @param  ToolResult[]  $toolResults
     * @return Message[]
     */
    private function appendToolRound(
        array $messages,
        \LLMesh\Core\Contracts\ResponseInterface $response,
        array $toolCalls,
        array $toolResults,
    ): array {
        // Encode the tool-call list as the assistant turn content
        $assistantContent = $response->getText() !== ''
            ? $response->getText()
            : json_encode(
                array_map(fn (ToolCall $tc) => $tc->toArray(), $toolCalls),
                JSON_THROW_ON_ERROR,
            );

        $messages[] = Message::assistant($assistantContent);

        foreach ($toolResults as $toolResult) {
            $messages[] = Message::tool(
                $toolResult->resultToString(),
                $toolResult->toolCallId,
                $toolResult->toolName,
            );
        }

        return $messages;
    }

    /**
     * Dispatch an event if an event dispatcher is configured.
     *
     * A no-op when no dispatcher is attached, so the agent works without
     * PSR-14 infrastructure.
     */
    private function dispatch(object $event): void
    {
        $this->eventDispatcher?->dispatch($event);
    }

    /**
     * Return the current time in milliseconds.
     */
    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
