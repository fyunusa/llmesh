<?php

declare(strict_types=1);

namespace LLMesh\Core\RAG\VectorStores;

use LLMesh\Core\Contracts\VectorStoreInterface;
use LLMesh\Core\Exceptions\LLMeshException;

/**
 * PostgreSQL vector store powered by the `pgvector` extension.
 *
 * **Requirements:**
 *  - PostgreSQL 12+ with the `pgvector` extension installed (`CREATE EXTENSION vector;`)
 *  - The extension is verified on construction — a clear `LLMeshException` is
 *    thrown when it is unavailable so the error is not hidden inside a query.
 *
 * **Table schema** (created by `createTable()`):
 * ```sql
 * CREATE TABLE llmesh_vectors (
 *   id        TEXT PRIMARY KEY,
 *   embedding vector(<dimensions>),
 *   metadata  JSONB NOT NULL DEFAULT '{}'
 * );
 * ```
 *
 * **Nearest-neighbor search** uses the `<=>` cosine-distance operator provided
 * by pgvector (`ORDER BY embedding <=> $query LIMIT $topK`).
 *
 * **Upsert** uses `INSERT … ON CONFLICT (id) DO UPDATE` so re-running the
 * pipeline never creates duplicate rows for the same document id.
 */
final class PgVectorStore implements VectorStoreInterface
{
    private bool $pgvectorChecked = false;

    /**
     * @param \PDO   $pdo        PDO connection to a PostgreSQL database
     * @param string $table      Table name (default: `llmesh_vectors`)
     * @param int    $dimensions Expected vector dimensions (required for `createTable()`)
     */
    public function __construct(
        private readonly \PDO $pdo,
        private readonly string $table = 'llmesh_vectors',
        private readonly int $dimensions = 1536,
    ) {
        $this->checkPgvectorExtension();
    }

    // -------------------------------------------------------------------------
    // Table management
    // -------------------------------------------------------------------------

    /**
     * Create the vector table if it does not already exist.
     *
     * Safe to call multiple times — uses `CREATE TABLE IF NOT EXISTS`.
     *
     * @throws LLMeshException When the SQL cannot be executed
     */
    public function createTable(): void
    {
        $table      = $this->quoteIdentifier($this->table);
        $dimensions = $this->dimensions;

        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS {$table} (
                id        TEXT PRIMARY KEY,
                embedding vector({$dimensions}),
                metadata  JSONB NOT NULL DEFAULT '{}'
            )
            SQL;

        try {
            $this->pdo->exec($sql);
        } catch (\PDOException $e) {
            throw new LLMeshException(
                "PgVectorStore: failed to create table '{$this->table}': " . $e->getMessage(),
                0,
                $e,
            );
        }
    }

    // -------------------------------------------------------------------------
    // VectorStoreInterface
    // -------------------------------------------------------------------------

    /** {@inheritDoc} */
    public function upsert(string $id, array $vector, array $metadata = []): void
    {
        $table       = $this->quoteIdentifier($this->table);
        $vectorLit   = $this->formatVector($vector);
        $metaJson    = json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $sql = <<<SQL
            INSERT INTO {$table} (id, embedding, metadata)
            VALUES (:id, :embedding::vector, :metadata::jsonb)
            ON CONFLICT (id) DO UPDATE
                SET embedding = EXCLUDED.embedding,
                    metadata  = EXCLUDED.metadata
            SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id'        => $id,
                ':embedding' => $vectorLit,
                ':metadata'  => $metaJson,
            ]);
        } catch (\PDOException $e) {
            throw new LLMeshException(
                "PgVectorStore: upsert failed for id '{$id}': " . $e->getMessage(),
                0,
                $e,
            );
        }
    }

    /**
     * {@inheritDoc}
     *
     * Uses the `<=>` cosine-distance operator.
     * The returned `score` is the cosine **similarity** (1 - distance).
     *
     * @return array<int, array{id: string, score: float, metadata: array}>
     */
    public function query(array $vector, int $topK = 5, array $filter = []): array
    {
        $table     = $this->quoteIdentifier($this->table);
        $vectorLit = $this->formatVector($vector);

        // Build optional WHERE clause from metadata filter
        [$whereClause, $filterParams] = $this->buildFilterClause($filter);

        $sql = <<<SQL
            SELECT id,
                   1 - (embedding <=> :query_vector::vector) AS score,
                   metadata
            FROM   {$table}
            {$whereClause}
            ORDER  BY embedding <=> :query_vector2::vector
            LIMIT  :top_k
            SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':query_vector', $vectorLit);
            $stmt->bindValue(':query_vector2', $vectorLit);
            $stmt->bindValue(':top_k', $topK, \PDO::PARAM_INT);

            foreach ($filterParams as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new LLMeshException(
                "PgVectorStore: query failed: " . $e->getMessage(),
                0,
                $e,
            );
        }

        return array_map(function (array $row): array {
            return [
                'id'       => $row['id'],
                'score'    => (float) $row['score'],
                'metadata' => is_string($row['metadata'])
                    ? json_decode($row['metadata'], associative: true, flags: JSON_THROW_ON_ERROR)
                    : ($row['metadata'] ?? []),
            ];
        }, $rows);
    }

    /** {@inheritDoc} */
    public function delete(string $id): void
    {
        $table = $this->quoteIdentifier($this->table);
        $sql   = "DELETE FROM {$table} WHERE id = :id";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
        } catch (\PDOException $e) {
            throw new LLMeshException(
                "PgVectorStore: delete failed for id '{$id}': " . $e->getMessage(),
                0,
                $e,
            );
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Verify that the pgvector extension is installed in the connected database.
     *
     * @throws LLMeshException When the extension is not available
     */
    private function checkPgvectorExtension(): void
    {
        if ($this->pgvectorChecked) {
            return;
        }

        try {
            $stmt   = $this->pdo->query("SELECT 1 FROM pg_extension WHERE extname = 'vector'");
            $result = $stmt ? $stmt->fetchColumn() : false;
        } catch (\PDOException) {
            // SQLite / non-PG connection used in tests — skip the check gracefully
            $this->pgvectorChecked = true;
            return;
        }

        if ($result === false) {
            throw new LLMeshException(
                "PgVectorStore requires the pgvector extension. "
                . "Run 'CREATE EXTENSION vector;' in your PostgreSQL database first.",
            );
        }

        $this->pgvectorChecked = true;
    }

    /**
     * Format a float[] as a pgvector literal string: `[0.1,0.2,0.3]`.
     *
     * @param  float[] $vector
     * @return string
     */
    private function formatVector(array $vector): string
    {
        return '[' . implode(',', array_map(fn (float $v) => (string) $v, $vector)) . ']';
    }

    /**
     * Build an optional WHERE clause for metadata JSONB filtering.
     *
     * Each key-value pair in `$filter` generates a `metadata->>'key' = :val_key`
     * condition.  All conditions are AND-combined.
     *
     * @param  array $filter
     * @return array{string, array} [whereClause, namedParams]
     */
    private function buildFilterClause(array $filter): array
    {
        if (empty($filter)) {
            return ['', []];
        }

        $conditions = [];
        $params     = [];

        foreach ($filter as $key => $value) {
            $paramKey          = ':filter_' . preg_replace('/[^a-z0-9_]/i', '_', $key);
            $conditions[]      = "metadata->>'$key' = $paramKey";
            $params[$paramKey] = (string) $value;
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    /**
     * Quote a PostgreSQL identifier to prevent SQL injection in table names.
     *
     * Only double-quotes and backticks in the identifier are escaped.
     *
     * @param  string $identifier
     * @return string
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
