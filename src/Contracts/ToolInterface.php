<?php

declare(strict_types=1);

namespace LLMesh\Core\Contracts;

/**
 * Interface for tools that can be called by LLM providers.
 */
interface ToolInterface
{
    /**
     * Get the tool name.
     *
     * @return string Unique identifier for the tool
     */
    public function getName(): string;

    /**
     * Get the tool description.
     *
     * @return string Human-readable description of what the tool does
     */
    public function getDescription(): string;

    /**
     * Get the parameter schema for this tool.
     *
     * @return array JSON Schema array describing the tool's parameters
     */
    public function getParameterSchema(): array;

    /**
     * Execute the tool with the given parameters.
     *
     * @param array $params Parameters to pass to the tool
     *
     * @return mixed The result of tool execution
     *
     * @throws \LLMesh\Core\Exceptions\ToolExecutionException On execution errors
     */
    public function execute(array $params): mixed;
}
