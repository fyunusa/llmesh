<?php

declare(strict_types=1);

namespace LLMesh\Core\Observability;

/**
 * Calculates estimated USD cost for LLM API calls.
 *
 * Pricing is stored per-model as `[inputPer1M, outputPer1M]` in USD.
 * The pricing table can be extended at runtime via `setPricing()`.
 *
 * Rules:
 *  - Returns `null` (never throws) for unknown models.
 *  - All prices are in USD per 1 000 000 tokens.
 *  - Thread-safe for read operations; `setPricing()` mutates shared state
 *    (acceptable for single-process PHP — no locking needed).
 *
 * @example
 * ```php
 * $cost = CostCalculator::calculate('gpt-4o', 1500, 800);
 * // → (1500 / 1_000_000 * 2.50) + (800 / 1_000_000 * 10.00)  = 0.003750 + 0.008000 = 0.011750
 * ```
 */
final class CostCalculator
{
    /**
     * Pricing table per model.
     * Format: 'model-id' => [input_cost_per_1m_tokens, output_cost_per_1m_tokens]
     * All prices are in USD.
     *
     * Sources:
     * - OpenAI:    https://openai.com/api/pricing
     * - Anthropic: https://www.anthropic.com/pricing
     *
     * @var array<string, array{float, float}>
     */
    private static array $pricing = [
        // OpenAI Chat Models
        // $2.50 per 1M input tokens, $10.00 per 1M output tokens
        'gpt-4o'                  => [2.50,  10.00],

        // $10.00 per 1M input tokens, $30.00 per 1M output tokens
        'gpt-4-turbo'             => [10.00, 30.00],

        // $0.50 per 1M input tokens, $1.50 per 1M output tokens
        'gpt-3.5-turbo'           => [0.50,  1.50],

        // $15.00 per 1M input tokens, $60.00 per 1M output tokens
        'o1'                      => [15.00, 60.00],

        // $3.00 per 1M input tokens, $12.00 per 1M output tokens
        'o1-mini'                 => [3.00,  12.00],

        // Anthropic Claude Models
        // $3.00 per 1M input tokens, $15.00 per 1M output tokens
        'claude-sonnet-4-5'       => [3.00,  15.00],

        // $15.00 per 1M input tokens, $75.00 per 1M output tokens
        'claude-opus-4-5'         => [15.00, 75.00],

        // $0.80 per 1M input tokens, $4.00 per 1M output tokens
        'claude-haiku-3-5'        => [0.80,  4.00],

        // OpenAI Embedding Models
        // $0.02 per 1M input tokens, $0.00 per 1M output tokens (embeddings have no output cost)
        'text-embedding-3-small'  => [0.02,  0.00],

        // $0.13 per 1M input tokens, $0.00 per 1M output tokens
        'text-embedding-3-large'  => [0.13,  0.00],

        // $0.10 per 1M input tokens, $0.00 per 1M output tokens
        'text-embedding-ada-002'  => [0.10,  0.00],
    ];

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Calculate the estimated cost for a given model and token counts.
     *
     * Formula: (inputTokens / 1_000_000 * inputCostPer1M) + (outputTokens / 1_000_000 * outputCostPer1M)
     *
     * @param string $model        The model identifier (e.g. 'gpt-4o', 'claude-sonnet-4-5')
     * @param int    $inputTokens  Number of input/prompt tokens consumed
     * @param int    $outputTokens Number of output/completion tokens generated
     *
     * @return float|null Estimated cost in USD, or null if the model is not in the pricing table
     */
    public static function calculate(string $model, int $inputTokens, int $outputTokens): ?float
    {
        $normalised = self::normalise($model);

        if (! isset(self::$pricing[$normalised])) {
            return null;
        }

        [$inputRate, $outputRate] = self::$pricing[$normalised];

        $cost = ($inputTokens / 1_000_000 * $inputRate)
              + ($outputTokens / 1_000_000 * $outputRate);

        // Round to 10 decimal places to avoid floating-point accumulation noise
        return round($cost, 10);
    }

    /**
     * Check whether a model has a known pricing entry.
     *
     * @param  string $model Model name
     * @return bool
     */
    public static function isKnownModel(string $model): bool
    {
        return isset(self::$pricing[self::normalise($model)]);
    }

    /**
     * Add or overwrite the pricing for a model.
     *
     * Useful for custom/fine-tuned models or when pricing changes.
     *
     * @param  string $model        Canonical model name
     * @param  float  $inputPer1M  Input cost in USD per 1 000 000 tokens
     * @param  float  $outputPer1M Output cost in USD per 1 000 000 tokens
     */
    public static function setPricing(string $model, float $inputPer1M, float $outputPer1M): void
    {
        self::$pricing[self::normalise($model)] = [$inputPer1M, $outputPer1M];
    }

    /**
     * Return a copy of the full pricing table.
     *
     * @return array<string, array{0: float, 1: float}>
     */
    public static function getPricingTable(): array
    {
        return self::$pricing;
    }

    /**
     * Reset the pricing table to built-in defaults.
     *
     * Primarily useful in tests.
     */
    public static function resetPricing(): void
    {
        // Reinitialise from the class definition by instantiating a fresh copy
        $defaults = (new \ReflectionProperty(self::class, 'pricing'))
            ->getDefaultValue();

        self::$pricing = $defaults;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Normalise a model name for lookup (lowercase, trimmed).
     */
    private static function normalise(string $model): string
    {
        return strtolower(trim($model));
    }
}
