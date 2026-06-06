<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

use LLMesh\Core\Agents\AgentResult;

/**
 * Dispatched when the agent loop terminates successfully (with or without
 * having reached `maxSteps`).
 */
final readonly class AgentFinished
{
    /**
     * @param AgentResult $result The complete result produced by the agent
     */
    public function __construct(
        public AgentResult $result,
    ) {
    }
}
