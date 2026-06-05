<?php

declare(strict_types=1);

namespace LLMesh\Core\Contracts;

/**
 * Interface for token usage information.
 */
interface UsageInterface
{
    /**
     * Get input token count.
     *
     * @return int Number of tokens in the input
     */
    public function getInputTokens(): int;

    /**
     * Get output token count.
     *
     * @return int Number of tokens in the output
     */
    public function getOutputTokens(): int;

    /**
     * Get total token count.
     *
     * @return int Total tokens (input + output)
     */
    public function getTotalTokens(): int;

    /**
     * Get estimated cost of the request.
     *
     * @return float|null Estimated cost in USD, or null if unable to calculate
     */
    public function getEstimatedCost(): ?float;
}
