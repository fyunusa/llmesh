<?php

declare(strict_types=1);

namespace LLMesh\Core\Structured;

use LLMesh\Core\Exceptions\ValidationException;

final class ExtractionOptions
{
    private function __construct(
        public readonly string $input,
        public readonly string $modelClass,
        public readonly ?string $systemPrompt,
        public readonly int $maxRetries,
        public readonly ?float $temperature,
        public readonly ?int $maxTokens,
    ) {}

    public static function make(): self
    {
        return new self(
            input: '',
            modelClass: '',
            systemPrompt: null,
            maxRetries: 3,
            temperature: 0.1,    // low temperature for consistent structured output
            maxTokens: null,
        );
    }

    public function withInput(string $input): self
    {
        return new self($input, $this->modelClass, $this->systemPrompt, $this->maxRetries, $this->temperature, $this->maxTokens);
    }

    public function into(string $modelClass): self
    {
        return new self($this->input, $modelClass, $this->systemPrompt, $this->maxRetries, $this->temperature, $this->maxTokens);
    }

    public function withSystemPrompt(string $prompt): self
    {
        return new self($this->input, $this->modelClass, $prompt, $this->maxRetries, $this->temperature, $this->maxTokens);
    }

    public function withMaxRetries(int $maxRetries): self
    {
        return new self($this->input, $this->modelClass, $this->systemPrompt, $maxRetries, $this->temperature, $this->maxTokens);
    }

    public function withTemperature(float $temperature): self
    {
        return new self($this->input, $this->modelClass, $this->systemPrompt, $this->maxRetries, $temperature, $this->maxTokens);
    }

    public function withMaxTokens(int $maxTokens): self
    {
        return new self($this->input, $this->modelClass, $this->systemPrompt, $this->maxRetries, $this->temperature, $maxTokens);
    }

    public function validate(): void
    {
        $errors = [];

        if (empty($this->input)) {
            $errors[] = 'Input text cannot be empty';
        }
        if (empty($this->modelClass)) {
            $errors[] = 'Target model class must be specified via ->into(MyModel::class)';
        }
        if (!empty($this->modelClass) && !is_subclass_of($this->modelClass, LLMModel::class)) {
            $errors[] = "{$this->modelClass} must extend LLMesh\\Core\\Structured\\LLMModel";
        }
        if ($this->maxRetries < 1 || $this->maxRetries > 10) {
            $errors[] = 'maxRetries must be between 1 and 10';
        }

        if (!empty($errors)) {
            throw new ValidationException(
                'Extraction options validation failed: ' . implode(', ', $errors),
                $errors
            );
        }
    }
}
