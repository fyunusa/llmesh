<?php

declare(strict_types=1);

namespace LLMesh\Core\Generators;

use LLMesh\Core\Contracts\MemoryStoreInterface;
use LLMesh\Core\Exceptions\ValidationException;

/**
 * Options for text generation.
 *
 * @psalm-immutable
 */
final class GenerateTextOptions
{
    /**
     * @param string|null             $prompt         User prompt for single-turn conversation
     * @param array                   $messages       Array of Message objects for multi-turn conversation
     * @param string|null             $system         System prompt
     * @param float|null              $temperature    Temperature for generation (0.0-2.0)
     * @param int|null                $maxTokens      Maximum tokens to generate
     * @param array                   $stopSequences  Sequences that should stop generation
     * @param array                   $tools          Tools available to the model
     * @param MemoryStoreInterface|null $memory       Memory store for conversation history
     * @param string|null             $sessionId      Session ID for memory retrieval
     * @param int                     $maxSteps       Maximum tool-use iterations (default 5)
     * @param \Closure|null           $onToolCall     Optional callback fired before each tool execution
     */
    public function __construct(
        public readonly string|null $prompt = null,
        public readonly array $messages = [],
        public readonly string|null $system = null,
        public readonly float|null $temperature = null,
        public readonly int|null $maxTokens = null,
        public readonly array $stopSequences = [],
        public readonly array $tools = [],
        public readonly MemoryStoreInterface|null $memory = null,
        public readonly string|null $sessionId = null,
        public readonly int $maxSteps = 5,
        public readonly ?\Closure $onToolCall = null,
    ) {
    }

    /**
     * Create a new GenerateTextOptions builder.
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Set the prompt (single-turn).
     *
     * @param string $prompt The user prompt
     * @return self
     */
    public function withPrompt(string $prompt): self
    {
        return new self(
            prompt: $prompt,
            messages: $this->messages,
            system: $this->system,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $this->stopSequences,
            tools: $this->tools,
            memory: $this->memory,
            sessionId: $this->sessionId,
            maxSteps: $this->maxSteps,
            onToolCall: $this->onToolCall,
        );
    }

    /**
     * Set the messages (multi-turn).
     *
     * @param array $messages Array of Message objects
     * @return self
     */
    public function withMessages(array $messages): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $messages,
            system: $this->system,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $this->stopSequences,
            tools: $this->tools,
            memory: $this->memory,
            sessionId: $this->sessionId,
            maxSteps: $this->maxSteps,
            onToolCall: $this->onToolCall,
        );
    }

    /**
     * Set the system prompt.
     *
     * @param string $system The system prompt
     * @return self
     */
    public function withSystem(string $system): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $this->messages,
            system: $system,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $this->stopSequences,
            tools: $this->tools,
            memory: $this->memory,
            sessionId: $this->sessionId,
            maxSteps: $this->maxSteps,
            onToolCall: $this->onToolCall,
        );
    }

    /**
     * Set the temperature.
     *
     * @param float $temperature Temperature value (typically 0.0-2.0)
     * @return self
     */
    public function withTemperature(float $temperature): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $this->messages,
            system: $this->system,
            temperature: $temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $this->stopSequences,
            tools: $this->tools,
            memory: $this->memory,
            sessionId: $this->sessionId,
            maxSteps: $this->maxSteps,
            onToolCall: $this->onToolCall,
        );
    }

    /**
     * Set the maximum tokens.
     *
     * @param int $maxTokens Maximum tokens to generate
     * @return self
     */
    public function withMaxTokens(int $maxTokens): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $this->messages,
            system: $this->system,
            temperature: $this->temperature,
            maxTokens: $maxTokens,
            stopSequences: $this->stopSequences,
            tools: $this->tools,
            memory: $this->memory,
            sessionId: $this->sessionId,
            maxSteps: $this->maxSteps,
            onToolCall: $this->onToolCall,
        );
    }

    /**
     * Set stop sequences.
     *
     * @param array $stopSequences Sequences to stop generation on
     * @return self
     */
    public function withStopSequences(array $stopSequences): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $this->messages,
            system: $this->system,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $stopSequences,
            tools: $this->tools,
            memory: $this->memory,
            sessionId: $this->sessionId,
            maxSteps: $this->maxSteps,
            onToolCall: $this->onToolCall,
        );
    }

    /**
     * Set the tools available to the model.
     *
     * @param array $tools Array of Tool objects (or raw tool definition arrays)
     * @return self
     */
    public function withTools(array $tools): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $this->messages,
            system: $this->system,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $this->stopSequences,
            tools: $tools,
            memory: $this->memory,
            sessionId: $this->sessionId,
            maxSteps: $this->maxSteps,
            onToolCall: $this->onToolCall,
        );
    }

    /**
     * Set the memory store and session ID.
     *
     * @param MemoryStoreInterface $memory Memory store instance
     * @param string $sessionId Session ID for this conversation
     * @return self
     */
    public function withMemory(MemoryStoreInterface $memory, string $sessionId): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $this->messages,
            system: $this->system,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $this->stopSequences,
            tools: $this->tools,
            memory: $memory,
            sessionId: $sessionId,
            maxSteps: $this->maxSteps,
            onToolCall: $this->onToolCall,
        );
    }

    /**
     * Set the maximum number of tool-use iterations.
     *
     * When the model requests tool calls, TextGenerator will loop at most
     * `maxSteps` times before returning the last response — even if the model
     * continues requesting tool calls.
     *
     * @param int $maxSteps Maximum iterations (>= 1)
     * @return self
     */
    public function withMaxSteps(int $maxSteps): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $this->messages,
            system: $this->system,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $this->stopSequences,
            tools: $this->tools,
            memory: $this->memory,
            sessionId: $this->sessionId,
            maxSteps: $maxSteps,
            onToolCall: $this->onToolCall,
        );
    }

    /**
     * Register a callback that is invoked immediately before each tool is executed.
     *
     * The callback receives the `\LLMesh\Core\Data\ToolCall` DTO so callers can
     * log, audit, or cancel individual tool invocations.
     *
     * @param \Closure(\LLMesh\Core\Data\ToolCall): void $callback
     * @return self
     */
    public function onToolCall(\Closure $callback): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $this->messages,
            system: $this->system,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $this->stopSequences,
            tools: $this->tools,
            memory: $this->memory,
            sessionId: $this->sessionId,
            maxSteps: $this->maxSteps,
            onToolCall: $callback,
        );
    }

    /**
     * Validate that at least one of prompt or messages is set.
     *
     * @throws ValidationException If neither prompt nor messages is set
     */
    public function validate(): void
    {
        if (!$this->prompt && empty($this->messages)) {
            throw new ValidationException(
                'Either prompt or messages must be provided',
                ['options' => 'At least one of "prompt" or "messages" is required'],
            );
        }
    }
}
