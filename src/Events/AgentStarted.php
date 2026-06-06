<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

use LLMesh\Core\Contracts\ProviderInterface;

/**
 * Dispatched immediately before the agent loop begins.
 */
final readonly class AgentStarted
{
    /**
     * @param ProviderInterface $provider    The provider that will be called
     * @param string            $systemPrompt System prompt given to the agent
     * @param string[]          $toolNames    Names of tools registered with the agent
     * @param int               $maxSteps     Hard ceiling on loop iterations
     */
    public function __construct(
        public ProviderInterface $provider,
        public string $systemPrompt,
        public array $toolNames,
        public int $maxSteps,
    ) {
    }
}
