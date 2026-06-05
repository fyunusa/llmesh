<?php

declare(strict_types=1);

namespace LLMesh\Core\Tools;

/**
 * Immutable result of a single tool execution.
 *
 * Carries the original tool-call identifiers (`toolCallId`, `toolName`),
 * the handler's return value or error message (`result`), and a flag
 * indicating whether the execution succeeded (`isError`).
 */
final readonly class ToolResult
{
    /**
     * @param string $toolCallId The LLM-assigned call ID (used when replying to the model)
     * @param string $toolName   Name of the executed tool
     * @param mixed  $result     Return value (success) or error message string (error)
     * @param bool   $isError    True when the tool threw an exception
     */
    public function __construct(
        public string $toolCallId,
        public string $toolName,
        public mixed $result,
        public bool $isError,
    ) {
    }

    // -------------------------------------------------------------------------
    // Named constructors
    // -------------------------------------------------------------------------

    /**
     * Create a successful tool result.
     *
     * @param string $id     The LLM-assigned tool call ID
     * @param string $name   Name of the executed tool
     * @param mixed  $result Whatever the handler returned
     */
    public static function success(string $id, string $name, mixed $result): self
    {
        return new self(
            toolCallId: $id,
            toolName: $name,
            result: $result,
            isError: false,
        );
    }

    /**
     * Create an error tool result.
     *
     * @param string $id           The LLM-assigned tool call ID
     * @param string $name         Name of the tool that failed
     * @param string $errorMessage Human-readable error description
     */
    public static function error(string $id, string $name, string $errorMessage): self
    {
        return new self(
            toolCallId: $id,
            toolName: $name,
            result: $errorMessage,
            isError: true,
        );
    }

    // -------------------------------------------------------------------------
    // Serialisation
    // -------------------------------------------------------------------------

    /**
     * Encode the result as a string suitable for a tool reply message.
     *
     * Scalars and strings are returned as-is; arrays/objects are JSON-encoded.
     */
    public function resultToString(): string
    {
        if (is_string($this->result)) {
            return $this->result;
        }

        return json_encode($this->result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
