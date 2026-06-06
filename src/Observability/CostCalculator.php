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
     * Default pricing table: model → [input $/1M, output $/1M].
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private static array $pricing = [
        // OpenAI chat models
        'gpt-4o'                    => [2.50,  10.00],
        'gpt-4o-mini'               => [0.15,   0.60],
        'gpt-4-turbo'               => [10.00, 30.00],
        'gpt-4-turbo-preview'       => [10.00, 30.00],
        'gpt-4'                     => [30.00, 60.00],
        'gpt-3.5-turbo'             => [0.50,   1.50],
        'gpt-3.5-turbo-0125'        => [0.50,   1.50],

        // OpenAI o-series
        'o1'                        => [15.00, 60.00],
        'o1-mini'                   => [3.00,  12.00],
        'o3-mini'                   => [1.10,   4.40],

        // OpenAI embedding models
        'text-embedding-3-small'    => [0.02,   0.00],
        'text-embedding-3-large'    => [0.13,   0.00],
        'text-embedding-ada-002'    => [0.10,   0.00],

        // Anthropic Claude models
        'claude-opus-4-5'           => [15.00, 75.00],
        'claude-sonnet-4-5'         => [3.00,  15.00],
        'claude-haiku-4-5'          => [0.80,   4.00],
        'claude-3-opus-20240229'    => [15.00, 75.00],
        'claude-3-sonnet-20240229'  => [3.00,  15.00],
        'claude-3-haiku-20240307'   => [0.25,   1.25],

        // Groq (free-tier pricing; may differ)
        'llama3-8b-8192'            => [0.05,   0.08],
        'llama3-70b-8192'           => [0.59,   0.79],
        'mixtral-8x7b-32768'        => [0.27,   0.27],
        'gemma-7b-it'               => [0.10,   0.10],
    ];

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Calculate the estimated cost for a request.
     *
     * @param  string  $model        Canonical model name (e.g. 'gpt-4o')
     * @param  int     $inputTokens  Number of prompt/input tokens
     * @param  int     $outputTokens Number of completion/output tokens
     *
     * @return float|null Estimated cost in USD, or `null` if model is unknown
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
