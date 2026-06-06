<?php

declare(strict_types=1);

namespace LLMesh\Core\Memory;

use LLMesh\Core\Contracts\MemoryStoreInterface;
use LLMesh\Core\Exceptions\LLMeshException;

/**
 * PDO-backed conversation store.
 *
 * Works with MySQL, PostgreSQL, and SQLite via a plain `\PDO` connection.
 *
 * Table schema (created by `createTable()`):
 * ```sql
 * CREATE TABLE llmesh_memory (
 *     id            INTEGER PRIMARY KEY AUTOINCREMENT,
 *     session_id    VARCHAR(255) NOT NULL,
 *     message_index INT NOT NULL,
 *     role          VARCHAR(50)  NOT NULL,
 *     content       TEXT         NOT NULL,
 *     metadata      TEXT         DEFAULT NULL,   -- JSON
 *     created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
 * );
 * ```
 *
 * > **Important**: Call `createTable()` once manually or from a migration.
 * > The store does **not** auto-create the table to prevent surprise schema
 * > changes in production.
 */
final class DatabaseStore implements MemoryStoreInterface
{
    /**
     * @param \PDO   $pdo   An open PDO connection
     * @param string $table Table name (default 'llmesh_memory')
     */
    public function __construct(
        private readonly \PDO   $pdo,
        private readonly string $table = 'llmesh_memory',
    ) {
    }

    // -------------------------------------------------------------------------
    // Schema management
    // -------------------------------------------------------------------------

    /**
     * Create the storage table if it does not already exist.
     *
     * Call this once during application set-up or inside a migration.  Supports
     * MySQL, PostgreSQL (uses SERIAL primary key), and SQLite (AUTOINCREMENT).
     */
    public function createTable(): void
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $sql = match ($driver) {
            'pgsql'  => $this->createTableSqlPostgres(),
            default  => $this->createTableSqlDefault(),
        };

        try {
            $this->pdo->exec($sql);
        } catch (\PDOException $e) {
            throw new LLMeshException(
                'DatabaseStore: failed to create table "' . $this->table . '": ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    // -------------------------------------------------------------------------
    // MemoryStoreInterface
    // -------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * Computes the next `message_index` for the session by counting existing
     * rows, then inserts the new message.
     */
    public function append(string $sessionId, array $message): void
    {
        try {
            // Determine the next index for this session
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM ' . $this->table . ' WHERE session_id = ?',
            );
            $stmt->execute([$sessionId]);
            $nextIndex = (int) $stmt->fetchColumn();

            $metadata = null;
            if (!empty($message['toolCallId']) || !empty($message['toolName'])) {
                $metadata = json_encode([
                    'toolCallId' => $message['toolCallId'] ?? null,
                    'toolName'   => $message['toolName'] ?? null,
                ], JSON_THROW_ON_ERROR);
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO ' . $this->table
                . ' (session_id, message_index, role, content, metadata)'
                . ' VALUES (?, ?, ?, ?, ?)',
            );
            $insert->execute([
                $sessionId,
                $nextIndex,
                $message['role'] ?? '',
                $message['content'] ?? '',
                $metadata,
            ]);
        } catch (LLMeshException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new LLMeshException(
                'DatabaseStore: failed to append message for session "' . $sessionId . '": ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * {@inheritDoc}
     *
     * Rows are returned ordered by `message_index` ASC.  Each row is
     * transformed back into the array format expected by `TextGenerator`.
     */
    public function get(string $sessionId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT role, content, metadata FROM ' . $this->table
                . ' WHERE session_id = ? ORDER BY message_index ASC',
            );
            $stmt->execute([$sessionId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return array_map(function (array $row): array {
                $meta = $row['metadata'] !== null
                    ? json_decode((string) $row['metadata'], associative: true)
                    : [];

                return [
                    'role'       => $row['role'],
                    'content'    => $row['content'],
                    'toolCallId' => $meta['toolCallId'] ?? null,
                    'toolName'   => $meta['toolName'] ?? null,
                ];
            }, $rows);
        } catch (\Throwable $e) {
            throw new LLMeshException(
                'DatabaseStore: failed to retrieve messages for session "' . $sessionId . '": ' . $e->getMessage(),
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
            $stmt = $this->pdo->prepare(
                'DELETE FROM ' . $this->table . ' WHERE session_id = ?',
            );
            $stmt->execute([$sessionId]);
        } catch (\Throwable $e) {
            throw new LLMeshException(
                'DatabaseStore: failed to clear session "' . $sessionId . '": ' . $e->getMessage(),
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
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM ' . $this->table . ' WHERE session_id = ?',
            );
            $stmt->execute([$sessionId]);

            return ((int) $stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            throw new LLMeshException(
                'DatabaseStore: failed to check existence of session "' . $sessionId . '": ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    // -------------------------------------------------------------------------
    // Schema helpers
    // -------------------------------------------------------------------------

    /**
     * DDL for SQLite / MySQL.
     */
    private function createTableSqlDefault(): string
    {
        return <<<SQL
        CREATE TABLE IF NOT EXISTS {$this->table} (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id    VARCHAR(255) NOT NULL,
            message_index INT          NOT NULL,
            role          VARCHAR(50)  NOT NULL,
            content       TEXT         NOT NULL,
            metadata      TEXT         DEFAULT NULL,
            created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        )
        SQL;
    }

    /**
     * DDL for PostgreSQL (uses SERIAL instead of AUTOINCREMENT).
     */
    private function createTableSqlPostgres(): string
    {
        return <<<SQL
        CREATE TABLE IF NOT EXISTS {$this->table} (
            id            SERIAL PRIMARY KEY,
            session_id    VARCHAR(255) NOT NULL,
            message_index INT          NOT NULL,
            role          VARCHAR(50)  NOT NULL,
            content       TEXT         NOT NULL,
            metadata      TEXT         DEFAULT NULL,
            created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        )
        SQL;
    }
}
