<?php

declare(strict_types=1);

namespace LLMesh\Core\Tools;

use LLMesh\Core\Data\ToolCall;
use LLMesh\Core\Exceptions\ValidationException;

/**
 * Executes tool calls dispatched by the LLM.
 *
 * Matches each {@see ToolCall} by name against the registered {@see Tool}
 * instances, validates required parameters, calls the handler, and wraps
 * any exception in a {@see ToolResult::error()} so errors never bubble up
 * uncaught into the generation loop.
 */
final class ToolExecutor
{
    /**
     * Execute a single tool call.
     *
     * @param ToolCall $toolCall The tool call DTO from the LLM response
     * @param Tool[]   $tools    Available tool instances (keyed by any index)
     *
     * @return ToolResult A successful or error result — never throws
     */
    public function execute(ToolCall $toolCall, array $tools): ToolResult
    {
        $tool = $this->findTool($toolCall->name, $tools);

        if ($tool === null) {
            return ToolResult::error(
                $toolCall->id,
                $toolCall->name,
                "Unknown tool \"{$toolCall->name}\". Available tools: "
                    . implode(', ', array_map(fn (Tool $t) => $t->getName(), $tools)),
            );
        }

        try {
            $result = $tool->execute($toolCall->arguments);
            return ToolResult::success($toolCall->id, $toolCall->name, $result);
        } catch (ValidationException $e) {
            // Missing required params — return as error, do not rethrow
            return ToolResult::error($toolCall->id, $toolCall->name, $e->getMessage());
        } catch (\Throwable $e) {
            // ToolExecutionException and any other unexpected errors
            return ToolResult::error($toolCall->id, $toolCall->name, $e->getMessage());
        }
    }

    /**
     * Execute all tool calls in the array and return results in the same order.
     *
     * Execution is synchronous and sequential. Each call is isolated — an error
     * in one tool does not prevent the others from running.
     *
     * @param ToolCall[] $toolCalls Ordered list of tool calls to execute
     * @param Tool[]     $tools     Available tool instances
     *
     * @return ToolResult[] One result per tool call, in the same order
     */
    public function executeAll(array $toolCalls, array $tools): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $results[] = $this->execute($toolCall, $tools);
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Find a tool by name from the given array.
     *
     * @param  Tool[] $tools
     */
    private function findTool(string $name, array $tools): ?Tool
    {
        foreach ($tools as $tool) {
            if ($tool->getName() === $name) {
                return $tool;
            }
        }

        return null;
    }
}
