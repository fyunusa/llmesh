<?php

declare(strict_types=1);

namespace LLMesh\Core\Events;

use LLMesh\Core\Data\ToolCall;
use LLMesh\Core\Tools\ToolResult;

/**
 * Dispatched once for every individual tool call executed during the agent loop.
 *
 * Multiple `AgentToolCalled` events may be dispatched within a single step when
 * the model requests parallel tool calls.
 */
final readonly class AgentToolCalled
{
    /**
     * @param ToolCall   $toolCall   The tool call requested by the model
     * @param ToolResult $toolResult The result returned by the local handler
     * @param int        $stepNumber The 1-based step number this call belongs to
     */
    public function __construct(
        public ToolCall $toolCall,
        public ToolResult $toolResult,
        public int $stepNumber,
    ) {
    }
}
