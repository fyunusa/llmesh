<?php

declare(strict_types=1);

namespace LLMesh\Core\Memory;

use LLMesh\Core\Contracts\MemoryStoreInterface;

/**
 * In-memory conversation store.
 *
 * Stores messages in a private associative array for the lifetime of the
 * current PHP process.  Suitable for unit tests, short-lived scripts, and
 * single-request applications where persistence is not required.
 *
 * Thread-safety: PHP is single-threaded per-process, so no locking is needed.
 */
final class InMemoryStore implements MemoryStoreInterface
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $sessions = [];

    /**
     * Append a message to a conversation session.
     *
     * @param string               $sessionId Unique session identifier
     * @param array<string, mixed> $message   Message data (e.g. from Message::toArray())
     */
    public function append(string $sessionId, array $message): void
    {
        $this->sessions[$sessionId][] = $message;
    }

    /**
     * Retrieve all messages for a session.
     *
     * @param  string $sessionId Unique session identifier
     * @return array<int, array<string, mixed>> Empty array when session not found
     */
    public function get(string $sessionId): array
    {
        return $this->sessions[$sessionId] ?? [];
    }

    /**
     * Delete all messages for a session.
     *
     * @param string $sessionId Unique session identifier
     */
    public function clear(string $sessionId): void
    {
        unset($this->sessions[$sessionId]);
    }

    /**
     * Check whether a session has any stored messages.
     *
     * @param  string $sessionId Unique session identifier
     * @return bool   True when the session exists and has at least one message
     */
    public function exists(string $sessionId): bool
    {
        return isset($this->sessions[$sessionId]) && count($this->sessions[$sessionId]) > 0;
    }
}
