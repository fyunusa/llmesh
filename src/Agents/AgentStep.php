<?php

declare(strict_types=1);

namespace LLMesh\Core\Agents;

use LLMesh\Core\Contracts\ResponseInterface;
use LLMesh\Core\Data\ToolCall;
use LLMesh\Core\Tools\ToolResult;

/**
 * Immutable snapshot of a single iteration inside the agent loop.
 *
 * One `AgentStep` is produced each time the provider is called:
 *  - If the response contained tool calls the step records them plus their results.
 *  - If the response was a final answer the `toolCalls` / `toolResults` arrays are empty.
 */
final readonly class AgentStep
{
    /**
     * @param int               $stepNumber  1-based step counter
     * @param array             $input       Full message array sent to the provider for this step
     * @param ResponseInterface $output      Raw provider response for this step
     * @param ToolCall[]        $toolCalls   Tool calls extracted from `$output` (empty when no tools used)
     * @param ToolResult[]      $toolResults Results of executing each tool call (same order as `$toolCalls`)
     * @param int               $durationMs  Wall-clock time for the provider call, in milliseconds
     */
    public function __construct(
        public int               $stepNumber,
        public array             $input,
        public ResponseInterface $output,
        public array             $toolCalls   = [],
        public array             $toolResults = [],
        public int               $durationMs  = 0,
    ) {
    }

    /**
     * Convert to a fully serializable array suitable for audit logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'step_number'  => $this->stepNumber,
            'duration_ms'  => $this->durationMs,
            'finish_reason' => $this->output->getFinishReason(),
            'text'         => $this->output->getText(),
            'usage'        => [
                'input_tokens'  => $this->output->getUsage()->getInputTokens(),
                'output_tokens' => $this->output->getUsage()->getOutputTokens(),
                'total_tokens'  => $this->output->getUsage()->getTotalTokens(),
            ],
            'tool_calls'   => array_map(
                fn (ToolCall $tc) => $tc->toArray(),
                $this->toolCalls,
            ),
            'tool_results' => array_map(
                fn (ToolResult $tr) => [
                    'tool_call_id' => $tr->toolCallId,
                    'tool_name'    => $tr->toolName,
                    'result'       => $tr->result,
                    'is_error'     => $tr->isError,
                ],
                $this->toolResults,
            ),
        ];
    }
}
