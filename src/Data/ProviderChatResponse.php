<?php

declare(strict_types=1);

namespace LLMesh\Core\Data;

use LLMesh\Core\Contracts\ResponseInterface;
use LLMesh\Core\Contracts\UsageInterface;
use LLMesh\Core\Generators\Usage;

/**
 * Decoupled data object representing the raw response from a provider.
 */
final class ProviderChatResponse implements ResponseInterface
{
    private readonly UsageInterface $usage;

    /**
     * @param string $text
     * @param int $inputTokens
     * @param int $outputTokens
     * @param string $finishReason
     * @param array $raw
     */
    public function __construct(
        private readonly string $text,
        int $inputTokens,
        int $outputTokens,
        private readonly string $finishReason,
        private readonly array $raw,
    ) {
        $model = $raw['model'] ?? null;
        if ($model !== null) {
            $this->usage = Usage::forModel($model, $inputTokens, $outputTokens);
        } else {
            $this->usage = new Usage($inputTokens, $outputTokens);
        }
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
}
