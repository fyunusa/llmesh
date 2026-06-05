<?php

declare(strict_types=1);

namespace LLMesh\Core\Contracts;

/**
 * Interface for conversation memory storage.
 *
 * Implementations can store messages in memory, databases, Redis, etc.
 */
interface MemoryStoreInterface
{
    /**
     * Append a message to a conversation session.
     *
     * @param string $sessionId Unique identifier for the conversation session
     * @param array $message Message data (typically from Message DTO)
     *
     * @return void
     *
     * @throws \LLMesh\Core\Exceptions\LLMeshException On storage errors
     */
    public function append(string $sessionId, array $message): void;

    /**
     * Retrieve all messages from a conversation session.
     *
     * @param string $sessionId Unique identifier for the conversation session
     *
     * @return array Array of messages, or empty array if session not found
     *
     * @throws \LLMesh\Core\Exceptions\LLMeshException On storage errors
     */
    public function get(string $sessionId): array;

    /**
     * Clear all messages from a conversation session.
     *
     * @param string $sessionId Unique identifier for the conversation session
     *
     * @return void
     *
     * @throws \LLMesh\Core\Exceptions\LLMeshException On storage errors
     */
    public function clear(string $sessionId): void;

    /**
     * Check if a conversation session exists.
     *
     * @param string $sessionId Unique identifier for the conversation session
     *
     * @return bool True if session has messages, false otherwise
     *
     * @throws \LLMesh\Core\Exceptions\LLMeshException On storage errors
     */
    public function exists(string $sessionId): bool;
}
