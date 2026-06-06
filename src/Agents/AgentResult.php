<?php

declare(strict_types=1);

namespace LLMesh\Core\Agents;

use LLMesh\Core\Contracts\UsageInterface;
use LLMesh\Core\Generators\Usage;

/**
 * Immutable result returned by `Agent::run()`.
 *
 * Aggregates all per-step information into a single, fully serializable object
 * that is convenient for audit logging, debugging, and downstream processing.
 */
final class AgentResult
{
    /**
     * @param string         $finalText   The model's last text response (the "answer")
     * @param AgentStep[]    $steps       All steps that were executed, in order
     * @param int            $totalSteps  Number of provider calls made (equal to `count($steps)`)
     * @param bool           $stoppedEarly True when the loop was terminated because `maxSteps` was reached
     *                                    before the model returned a non-tool-call finish reason
     * @param UsageInterface $usage        Aggregated token usage across all steps
     */
    public function __construct(
        public readonly string $finalText,
        public readonly array $steps,
        public readonly int $totalSteps,
        public readonly bool $stoppedEarly,
        public readonly UsageInterface $usage,
    ) {
    }

    // -------------------------------------------------------------------------
    // Convenience accessors
    // -------------------------------------------------------------------------

    /**
     * Return the number of steps that were executed.
     *
     * Equivalent to `count($this->steps)` — provided for readability.
     */
    public function getStepCount(): int
    {
        return count($this->steps);
    }

    /**
     * Return the aggregated estimated cost, or `null` when no provider
     * supplied cost information.
     */
    public function getTotalCost(): ?float
    {
        return $this->usage->getEstimatedCost();
    }

    // -------------------------------------------------------------------------
    // Serialisation
    // -------------------------------------------------------------------------

    /**
     * Convert the entire result to a fully serializable array.
     *
     * Suitable for JSON-encoding and audit logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'final_text'    => $this->finalText,
            'total_steps'   => $this->totalSteps,
            'stopped_early' => $this->stoppedEarly,
            'usage'         => [
                'input_tokens'   => $this->usage->getInputTokens(),
                'output_tokens'  => $this->usage->getOutputTokens(),
                'total_tokens'   => $this->usage->getTotalTokens(),
                'estimated_cost' => $this->usage->getEstimatedCost(),
            ],
            'steps' => array_map(
                fn (AgentStep $s) => $s->toArray(),
                $this->steps,
            ),
        ];
    }

    // -------------------------------------------------------------------------
    // Internal factory
    // -------------------------------------------------------------------------

    /**
     * Build an `AgentResult` by aggregating token usage across all steps.
     *
     * @param string      $finalText    The model's last text response
     * @param AgentStep[] $steps        All completed steps
     * @param bool        $stoppedEarly Whether the loop was cut short by maxSteps
     * @return self
     */
    public static function fromSteps(
        string $finalText,
        array $steps,
        bool $stoppedEarly,
    ): self {
        $inputTokens  = 0;
        $outputTokens = 0;
        $totalCost    = 0.0;
        $anyCostNull  = false;

        foreach ($steps as $step) {
            $u = $step->output->getUsage();
            $inputTokens  += $u->getInputTokens();
            $outputTokens += $u->getOutputTokens();

            $cost = $u->getEstimatedCost();
            if ($cost === null) {
                $anyCostNull = true;
            } else {
                $totalCost += $cost;
            }
        }

        $aggregatedUsage = new Usage(
            inputTokens:   $inputTokens,
            outputTokens:  $outputTokens,
            estimatedCost: $anyCostNull ? null : $totalCost,
        );

        return new self(
            finalText:    $finalText,
            steps:        $steps,
            totalSteps:   count($steps),
            stoppedEarly: $stoppedEarly,
            usage:        $aggregatedUsage,
        );
    }
}
