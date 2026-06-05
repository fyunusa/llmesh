<?php

declare(strict_types=1);

namespace LLMesh\Core\Generators;

use LLMesh\Core\Contracts\ResponseInterface;
use LLMesh\Core\Contracts\UsageInterface;

/**
 * Response containing generated text.
 *
 * @psalm-immutable
 */
final readonly class TextResponse implements ResponseInterface
{
    /**
     * @param string $text The generated text
     * @param UsageInterface $usage Token usage information
     * @param string $finishReason Reason why generation stopped (e.g., 'stop', 'length', 'tool_calls')
     * @param array $raw Raw provider response data
     */
    public function __construct(
        public string $text,
        public UsageInterface $usage,
        public string $finishReason,
        public array $raw,
    ) {
    }

    /**
     * Create TextResponse from a provider response using a parser.
     *
     * The parser callable is responsible for extracting text, usage, and finishReason
     * from the provider-specific response format.
     *
     * Parser should return: ['text' => string, 'usage' => array, 'finishReason' => string]
     *
     * @param array $raw Raw provider response
     * @param callable(array): array{text: string, usage: array, finishReason: string} $parser
     */
    public static function fromProviderResponse(array $raw, callable $parser): self
    {
        $parsed = $parser($raw);

        return new self(
            text: $parsed['text'],
            usage: Usage::fromArray($parsed['usage']),
            finishReason: $parsed['finishReason'],
            raw: $raw,
        );
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getUsage(): UsageInterface
    {
        return $this->usage;
    }

    public function getFinishReason(): string
    {
        return $this->finishReason;
    }

    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{text: string, usage: array, finish_reason: string, raw: array}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'usage' => $this->usage->toArray(),
            'finish_reason' => $this->finishReason,
            'raw' => $this->raw,
        ];
    }
}
