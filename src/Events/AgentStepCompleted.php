<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

use LLMesh\Core\Agents\AgentStep;

/**
 * Dispatched after each completed reasoning step of the agent loop
 * (whether the step ended in a tool call or a final answer).
 */
final class AgentStepCompleted
{
    /**
     * @param AgentStep $step The completed step DTO
     */
    public function __construct(
        public readonly AgentStep $step,
    ) {
    }
}
