<?php

declare(strict_types=1);

namespace LLMesh\Core\Memory;

use LLMesh\Core\Contracts\MemoryStoreInterface;
use LLMesh\Core\Exceptions\LLMeshException;

/**
 * Redis-backed conversation store.
 *
 * Works with both the `ext-redis` PHP extension (`\Redis`) and the
 * `predis/predis` Composer package (`\Predis\Client`).  Duck-typing is used
 * so that neither library is a hard dependency of llmesh/core.
 *
 * Messages are serialised as a JSON-encoded array and stored under the key
 * `{prefix}{sessionId}`.  A configurable TTL (default 3 600 s / 1 h) is
 * refreshed on every `append()`.
 *
 * @example
 *   $store = new RedisStore($redis, prefix: 'chat:', ttl: 7200);
 */
final class RedisStore implements MemoryStoreInterface
{
    /**
     * @param \Redis|\Predis\Client $redis  An already-connected Redis client
     * @param string                $prefix Key prefix for all session entries (default 'llmesh:memory:')
     * @param int                   $ttl    Time-to-live in seconds; refreshed on every append (default 3600)
     */
    public function __construct(
        private readonly mixed  $redis,
        private readonly string $prefix = 'llmesh:memory:',
        private readonly int    $ttl    = 3600,
    ) {
    }

    // -------------------------------------------------------------------------
    // MemoryStoreInterface
    // -------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * Loads the current message list, pushes the new message, then saves the
     * result back.  The TTL is extended on every write so active sessions
     * never expire mid-conversation.
     */
    public function append(string $sessionId, array $message): void
    {
        try {
            $key      = $this->buildKey($sessionId);
            $existing = $this->fetchMessages($key);
            $existing[] = $message;
            $this->storeMessages($key, $existing);
        } catch (LLMeshException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new LLMeshException(
                'RedisStore: failed to append message for session "' . $sessionId . '": ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $sessionId): array
    {
        try {
            return $this->fetchMessages($this->buildKey($sessionId));
        } catch (LLMeshException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new LLMeshException(
                'RedisStore: failed to retrieve messages for session "' . $sessionId . '": ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function clear(string $sessionId): void
    {
        try {
            $this->redis->del($this->buildKey($sessionId));
        } catch (\Throwable $e) {
            throw new LLMeshException(
                'RedisStore: failed to clear session "' . $sessionId . '": ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function exists(string $sessionId): bool
    {
        try {
            return (bool) $this->redis->exists($this->buildKey($sessionId));
        } catch (\Throwable $e) {
            throw new LLMeshException(
                'RedisStore: failed to check existence of session "' . $sessionId . '": ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the Redis key for a given session.
     */
    private function buildKey(string $sessionId): string
    {
        return $this->prefix . $sessionId;
    }

    /**
     * Read and JSON-decode the message list stored under $key.
     *
     * Returns an empty array when the key does not exist.
     *
     * @param  string $key Full Redis key
     * @return array<int, array<string, mixed>>
     */
    private function fetchMessages(string $key): array
    {
        $raw = $this->redis->get($key);

        if ($raw === false || $raw === null) {
            return [];
        }

        $decoded = json_decode((string) $raw, associative: true);

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * JSON-encode the message list and write it back to Redis with the TTL.
     *
     * @param string                            $key      Full Redis key
     * @param array<int, array<string, mixed>>  $messages Messages to persist
     */
    private function storeMessages(string $key, array $messages): void
    {
        $encoded = json_encode($messages, JSON_THROW_ON_ERROR);
        $this->redis->setex($key, $this->ttl, $encoded);
    }
}
