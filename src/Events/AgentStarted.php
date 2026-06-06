<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

use LLMesh\Core\Contracts\ProviderInterface;

/**
 * Dispatched immediately before the agent loop begins.
 */
final class AgentStarted
{
    /**
     * @param ProviderInterface $provider    The provider that will be called
     * @param string            $systemPrompt System prompt given to the agent
     * @param string[]          $toolNames    Names of tools registered with the agent
     * @param int               $maxSteps     Hard ceiling on loop iterations
     */
    public function __construct(
        public readonly ProviderInterface $provider,
        public readonly string $systemPrompt,
        public readonly array $toolNames,
        public readonly int $maxSteps,
    ) {
    }
}
