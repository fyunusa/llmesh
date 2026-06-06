<?php

declare(strict_types=1);

namespace LLMesh\Core\Generators;

use LLMesh\Core\Contracts\UsageInterface;
use LLMesh\Core\Observability\CostCalculator;

/**
 * Usage information for a text generation response.
 *
 * When a `model` is provided and `estimatedCost` is not set explicitly,
 * the cost is auto-calculated via `CostCalculator`. Returns `null` cost
 * (never throws) when the model is unknown.
 *
 * @psalm-immutable
 */
final class Usage implements UsageInterface
{
    /**
     * @param int        $inputTokens   Number of input tokens used
     * @param int        $outputTokens  Number of output tokens generated
     * @param int|null   $totalTokens   Total tokens used (auto-calculated if null)
     * @param float|null $estimatedCost Estimated USD cost (auto-calculated if null and model provided)
     */
    public function __construct(
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int|null $totalTokens = null,
        public readonly float|null $estimatedCost = null,
    ) {
    }

    /**
     * Create Usage from an array, optionally auto-calculating cost.
     *
     * If `estimated_cost` is absent and a `model` key is present,
     * `CostCalculator` is consulted to fill in the cost.
     *
     * @param array{
     *     input_tokens?: int,
     *     output_tokens?: int,
     *     total_tokens?: int|null,
     *     estimated_cost?: float|null,
     *     model?: string,
     * } $data
     */
    public static function fromArray(array $data): self
    {
        $inputTokens  = $data['input_tokens']  ?? 0;
        $outputTokens = $data['output_tokens'] ?? 0;
        $totalTokens  = $data['total_tokens']  ?? ($inputTokens + $outputTokens);

        // Use the explicitly provided cost, or auto-calculate when a model is known
        $estimatedCost = $data['estimated_cost'] ?? null;
        if ($estimatedCost === null && isset($data['model'])) {
            $estimatedCost = CostCalculator::calculate(
                $data['model'],
                $inputTokens,
                $outputTokens,
            );
        }

        return new self(
            inputTokens:   $inputTokens,
            outputTokens:  $outputTokens,
            totalTokens:   $totalTokens,
            estimatedCost: $estimatedCost,
        );
    }

    /**
     * Create Usage with automatic cost calculation for a known model.
     *
     * Convenience factory — equivalent to `fromArray` with a `model` key.
     *
     * @param string   $model        Model name (e.g. 'gpt-4o')
     * @param int      $inputTokens  Number of prompt tokens
     * @param int      $outputTokens Number of completion tokens
     * @param int|null $totalTokens  Total tokens (auto-derived if null)
     */
    public static function forModel(
        string $model,
        int $inputTokens,
        int $outputTokens,
        ?int $totalTokens = null,
    ): self {
        return new self(
            inputTokens:   $inputTokens,
            outputTokens:  $outputTokens,
            totalTokens:   $totalTokens,
            estimatedCost: CostCalculator::calculate($model, $inputTokens, $outputTokens),
        );
    }

    public function getInputTokens(): int
    {
        return $this->inputTokens;
    }

    public function getOutputTokens(): int
    {
        return $this->outputTokens;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens ?? ($this->inputTokens + $this->outputTokens);
    }

    public function getEstimatedCost(): float|null
    {
        return $this->estimatedCost;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{input_tokens: int, output_tokens: int, total_tokens: int, estimated_cost: float|null}
     */
    public function toArray(): array
    {
        return [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->getTotalTokens(),
            'estimated_cost' => $this->estimatedCost,
        ];
    }
}
