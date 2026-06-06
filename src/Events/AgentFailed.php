<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

use LLMesh\Core\Agents\AgentStep;

/**
 * Dispatched when an unrecoverable exception terminates the agent loop.
 *
 * The exception is also re-thrown after this event so callers can catch it
 * directly if they prefer not to use the event system.
 */
final readonly class AgentFailed
{
    /**
     * @param \Throwable  $exception     The exception that caused the failure
     * @param AgentStep[] $stepsComplete All steps that ran successfully before the failure
     */
    public function __construct(
        public \Throwable $exception,
        public array $stepsComplete,
    ) {
    }
}
