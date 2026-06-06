<?php

declare(strict_types=1);

namespace LLMesh\Core\Generators;

use LLMesh\Core\Contracts\MemoryStoreInterface;
use LLMesh\Core\Exceptions\ValidationException;
use LLMesh\Core\Schema\SchemaInterface;

/**
 * Options for object generation via `LLMesh::generateObject()`.
 *
 * Mirrors all fields from {@see GenerateTextOptions} and adds two more:
 *  - `schema`  (required) — the JSON Schema to validate the response against
 *  - `mode`    (optional) — JSON_MODE (default) or TOOL_MODE
 *
 * @psalm-immutable
 */
final class GenerateObjectOptions
{
    /**
     * @param string|null             $prompt         User prompt (single-turn)
     * @param array                   $messages       Message objects (multi-turn)
     * @param string|null             $system         System prompt prefix (merged with schema instruction)
     * @param float|null              $temperature    Generation temperature
     * @param int|null                $maxTokens      Maximum tokens
     * @param array                   $stopSequences  Stop sequences
     * @param SchemaInterface|null    $schema         JSON Schema to validate the response against
     * @param OutputMode              $mode           Output strategy
     * @param MemoryStoreInterface|null $memory       Memory store
     * @param string|null             $sessionId      Session ID for memory
     */
    public function __construct(
        public readonly string|null $prompt = null,
        public readonly array $messages = [],
        public readonly string|null $system = null,
        public readonly float|null $temperature = null,
        public readonly int|null $maxTokens = null,
        public readonly array $stopSequences = [],
        public readonly SchemaInterface|null $schema = null,
        public readonly OutputMode $mode = OutputMode::JSON_MODE,
        public readonly MemoryStoreInterface|null $memory = null,
        public readonly string|null $sessionId = null,
    ) {
    }

    // -------------------------------------------------------------------------
    // Factory + fluent builders
    // -------------------------------------------------------------------------

    public static function make(): self
    {
        return new self();
    }

    public function withPrompt(string $prompt): self
    {
        return new self(
            prompt: $prompt,
            messages: $this->messages,
            system: $this->system,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $this->stopSequences,
            schema: $this->schema,
            mode: $this->mode,
            memory: $this->memory,
            sessionId: $this->sessionId,
        );
    }

    public function withMessages(array $messages): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $messages,
            system: $this->system,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $this->stopSequences,
            schema: $this->schema,
            mode: $this->mode,
            memory: $this->memory,
            sessionId: $this->sessionId,
        );
    }

    public function withSystem(string $system): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $this->messages,
            system: $system,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $this->stopSequences,
            schema: $this->schema,
            mode: $this->mode,
            memory: $this->memory,
            sessionId: $this->sessionId,
        );
    }

    public function withTemperature(float $temperature): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $this->messages,
            system: $this->system,
            temperature: $temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $this->stopSequences,
            schema: $this->schema,
            mode: $this->mode,
            memory: $this->memory,
            sessionId: $this->sessionId,
        );
    }

    public function withMaxTokens(int $maxTokens): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $this->messages,
            system: $this->system,
            temperature: $this->temperature,
            maxTokens: $maxTokens,
            stopSequences: $this->stopSequences,
            schema: $this->schema,
            mode: $this->mode,
            memory: $this->memory,
            sessionId: $this->sessionId,
        );
    }

    public function withStopSequences(array $stopSequences): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $this->messages,
            system: $this->system,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $stopSequences,
            schema: $this->schema,
            mode: $this->mode,
            memory: $this->memory,
            sessionId: $this->sessionId,
        );
    }

    public function withSchema(SchemaInterface $schema): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $this->messages,
            system: $this->system,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $this->stopSequences,
            schema: $schema,
            mode: $this->mode,
            memory: $this->memory,
            sessionId: $this->sessionId,
        );
    }

    public function withMode(OutputMode $mode): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $this->messages,
            system: $this->system,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $this->stopSequences,
            schema: $this->schema,
            mode: $mode,
            memory: $this->memory,
            sessionId: $this->sessionId,
        );
    }

    public function withMemory(MemoryStoreInterface $memory, string $sessionId): self
    {
        return new self(
            prompt: $this->prompt,
            messages: $this->messages,
            system: $this->system,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            stopSequences: $this->stopSequences,
            schema: $this->schema,
            mode: $this->mode,
            memory: $memory,
            sessionId: $sessionId,
        );
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    /**
     * Validate that required fields are present.
     *
     * @throws ValidationException
     */
    public function validate(): void
    {
        if (!$this->prompt && empty($this->messages)) {
            throw new ValidationException(
                'Either prompt or messages must be provided',
                ['options' => 'At least one of "prompt" or "messages" is required'],
            );
        }

        if ($this->schema === null) {
            throw new ValidationException(
                'A schema must be provided for generateObject',
                ['schema' => 'schema is required'],
            );
        }
    }
}
