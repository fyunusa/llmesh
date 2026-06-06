<?php

declare(strict_types=1);

namespace LLMesh\Core\Data;

/**
 * Data Transfer Object for conversation messages.
 */
final class Message
{
    /**
     * Constructor.
     *
     * @param MessageRole $role The role of the message sender
     * @param string $content The message content
     * @param string|null $toolCallId Optional ID of the tool call being responded to
     * @param string|null $toolName Optional name of the tool that generated this message
     */
    public function __construct(
        public readonly MessageRole $role,
        public readonly string $content,
        public readonly ?string $toolCallId = null,
        public readonly ?string $toolName = null,
    ) {
    }

    /**
     * Create a user message.
     *
     * @param string $content The message content
     *
     * @return self
     */
    public static function user(string $content): self
    {
        return new self(
            role: MessageRole::USER,
            content: $content,
        );
    }

    /**
     * Create an assistant message.
     *
     * @param string $content The message content
     *
     * @return self
     */
    public static function assistant(string $content): self
    {
        return new self(
            role: MessageRole::ASSISTANT,
            content: $content,
        );
    }

    /**
     * Create a system message.
     *
     * @param string $content The message content
     *
     * @return self
     */
    public static function system(string $content): self
    {
        return new self(
            role: MessageRole::SYSTEM,
            content: $content,
        );
    }

    /**
     * Create a tool message.
     *
     * @param string $content The message content
     * @param string $toolCallId ID of the tool call being responded to
     * @param string $toolName Name of the tool that generated this message
     *
     * @return self
     */
    public static function tool(string $content, string $toolCallId, string $toolName): self
    {
        return new self(
            role: MessageRole::TOOL,
            content: $content,
            toolCallId: $toolCallId,
            toolName: $toolName,
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->content,
            'toolCallId' => $this->toolCallId,
            'toolName' => $this->toolName,
        ];
    }
}
