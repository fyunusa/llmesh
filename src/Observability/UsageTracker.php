<?php

declare(strict_types=1);

namespace LLMesh\Core\Observability;

use LLMesh\Core\Contracts\UsageInterface;

/**
 * Accumulates token usage and cost across multiple API calls in a session.
 *
 * Useful for tracking spend per user, per conversation, or per request batch.
 *
 * @example
 * ```php
 * $tracker = new UsageTracker();
 *
 * $tracker->record($response->getUsage());
 * $tracker->record($response2->getUsage());
 *
 * $summary = $tracker->getSummary();
 * // ['calls' => 2, 'tokens_in' => 1200, 'tokens_out' => 800, 'total_tokens' => 2000, 'cost_usd' => 0.042]
 * ```
 */
final class UsageTracker
{
    private int $totalInputTokens  = 0;
    private int $totalOutputTokens = 0;
    private float $totalCost         = 0.0;
    private int $callCount         = 0;

    /** @var UsageInterface[] */
    private array $records = [];

    /**
     * Record a `UsageInterface` instance.
     *
     * Adds token counts and cost (if available) to the running totals.
     *
     * @param UsageInterface $usage The usage from a single API call
     */
    public function record(UsageInterface $usage): void
    {
        $this->totalInputTokens  += $usage->getInputTokens();
        $this->totalOutputTokens += $usage->getOutputTokens();

        $cost = $usage->getEstimatedCost();
        if ($cost !== null) {
            $this->totalCost += $cost;
        }

        $this->callCount++;
        $this->records[] = $usage;
    }

    /**
     * Total number of input (prompt) tokens across all recorded calls.
     */
    public function getTotalInputTokens(): int
    {
        return $this->totalInputTokens;
    }

    /**
     * Total number of output (completion) tokens across all recorded calls.
     */
    public function getTotalOutputTokens(): int
    {
        return $this->totalOutputTokens;
    }

    /**
     * Sum of input + output tokens.
     */
    public function getTotalTokens(): int
    {
        return $this->totalInputTokens + $this->totalOutputTokens;
    }

    /**
     * Accumulated estimated cost in USD.
     *
     * Note: this only includes calls for which a cost estimate was available.
     */
    public function getTotalCost(): float
    {
        return $this->totalCost;
    }

    /**
     * Number of API calls recorded.
     */
    public function getCallCount(): int
    {
        return $this->callCount;
    }

    /**
     * All recorded `UsageInterface` objects in insertion order.
     *
     * @return UsageInterface[]
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * Return a JSON-serializable summary of accumulated usage.
     *
     * @return array{calls: int, tokens_in: int, tokens_out: int, total_tokens: int, cost_usd: float}
     */
    public function getSummary(): array
    {
        return [
            'calls'        => $this->callCount,
            'tokens_in'    => $this->totalInputTokens,
            'tokens_out'   => $this->totalOutputTokens,
            'total_tokens' => $this->getTotalTokens(),
            'cost_usd'     => round($this->totalCost, 10),
        ];
    }

    /**
     * Reset all counters and records.
     */
    public function reset(): void
    {
        $this->totalInputTokens  = 0;
        $this->totalOutputTokens = 0;
        $this->totalCost         = 0.0;
        $this->callCount         = 0;
        $this->records           = [];
    }
}
