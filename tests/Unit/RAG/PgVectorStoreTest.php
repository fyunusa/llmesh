<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\RAG;

use LLMesh\Core\Exceptions\LLMeshException;
use LLMesh\Core\RAG\VectorStores\PgVectorStore;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\RAG\VectorStores\PgVectorStore
 */
final class PgVectorStoreTest extends TestCase
{
    private function createMockPdo(bool $hasExtension = true, ?\PDOException $queryException = null): \PDO
    {
        $pdo = $this->createMock(\PDO::class);

        if ($queryException !== null) {
            $pdo->method('query')
                ->willThrowException($queryException);
        } else {
            $stmt = $this->createMock(\PDOStatement::class);
            $stmt->method('fetchColumn')
                ->willReturn($hasExtension ? '1' : false);

            $pdo->method('query')
                ->willReturn($stmt);
        }

        return $pdo;
    }

    public function testConstructorChecksExtensionAndSucceeds(): void
    {
        $pdo = $this->createMockPdo(true);
        $store = new PgVectorStore($pdo, 'my_table', 1536);
        $this->assertInstanceOf(PgVectorStore::class, $store);
    }

    public function testConstructorThrowsExceptionWhenExtensionMissing(): void
    {
        $pdo = $this->createMockPdo(false);

        $this->expectException(LLMeshException::class);
        $this->expectExceptionMessage('PgVectorStore requires the pgvector extension');

        new PgVectorStore($pdo, 'my_table', 1536);
    }

    public function testConstructorGracefullySkipsNonPostgresPdoException(): void
    {
        // For non-Postgres connections, a query to pg_extension throws a PDOException.
        // The constructor should handle this gracefully and complete.
        $pdo = $this->createMockPdo(false, new \PDOException('Driver does not support pg_extension'));
        $store = new PgVectorStore($pdo, 'my_table', 1536);
        $this->assertInstanceOf(PgVectorStore::class, $store);
    }

    public function testCreateTableExecutesCorrectSql(): void
    {
        $pdo = $this->createMockPdo(true);
        $pdo->expects($this->once())
            ->method('exec')
            ->with($this->callback(function ($sql) {
                return str_contains($sql, 'CREATE TABLE IF NOT EXISTS "my_table"')
                    && str_contains($sql, 'embedding vector(128)');
            }))
            ->willReturn(1);

        $store = new PgVectorStore($pdo, 'my_table', 128);
        $store->createTable();
    }

    public function testCreateTableThrowsOnFailure(): void
    {
        $pdo = $this->createMockPdo(true);
        $pdo->expects($this->once())
            ->method('exec')
            ->willThrowException(new \PDOException('Syntax error'));

        $store = new PgVectorStore($pdo, 'my_table', 128);

        $this->expectException(LLMeshException::class);
        $this->expectExceptionMessage("PgVectorStore: failed to create table 'my_table'");
        $store->createTable();
    }

    public function testUpsertPreparesAndExecutesCorrectSql(): void
    {
        $pdo = $this->createMockPdo(true);
        $stmt = $this->createMock(\PDOStatement::class);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                ':id' => 'doc-123',
                ':embedding' => '[0.1,0.2,-0.5]',
                ':metadata' => '{"key":"value"}',
            ])
            ->willReturn(true);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                return str_contains($sql, 'INSERT INTO "my_table"')
                    && str_contains($sql, 'ON CONFLICT (id) DO UPDATE');
            }))
            ->willReturn($stmt);

        $store = new PgVectorStore($pdo, 'my_table', 3);
        $store->upsert('doc-123', [0.1, 0.2, -0.5], ['key' => 'value']);
    }

    public function testUpsertThrowsOnFailure(): void
    {
        $pdo = $this->createMockPdo(true);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new \PDOException('Connection lost'));

        $store = new PgVectorStore($pdo, 'my_table', 3);

        $this->expectException(LLMeshException::class);
        $this->expectExceptionMessage("PgVectorStore: upsert failed for id 'doc-123'");
        $store->upsert('doc-123', [0.1, 0.2, -0.5]);
    }

    public function testDeletePreparesAndExecutesCorrectSql(): void
    {
        $pdo = $this->createMockPdo(true);
        $stmt = $this->createMock(\PDOStatement::class);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([':id' => 'doc-123'])
            ->willReturn(true);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM "my_table" WHERE id = :id')
            ->willReturn($stmt);

        $store = new PgVectorStore($pdo, 'my_table', 3);
        $store->delete('doc-123');
    }

    public function testDeleteThrowsOnFailure(): void
    {
        $pdo = $this->createMockPdo(true);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new \PDOException('Delete error'));

        $store = new PgVectorStore($pdo, 'my_table', 3);

        $this->expectException(LLMeshException::class);
        $this->expectExceptionMessage("PgVectorStore: delete failed for id 'doc-123'");
        $store->delete('doc-123');
    }

    public function testQueryPreparesAndBindsCorrectSqlWithFilters(): void
    {
        $pdo = $this->createMockPdo(true);
        $stmt = $this->createMock(\PDOStatement::class);

        // Expect query vector and top_k bound parameters
        $bindings = [];
        $stmt->expects($this->exactly(4))
            ->method('bindValue')
            ->willReturnCallback(function (...$args) use (&$bindings) {
                $bindings[] = $args;
                return true;
            });

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                [
                    'id' => 'doc-a',
                    'score' => 0.85,
                    'metadata' => '{"category":"tech","content":"test"}',
                ]
            ]);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                return str_contains($sql, 'SELECT id')
                    && str_contains($sql, 'WHERE metadata->>\'category\' = :filter_category')
                    && str_contains($sql, 'ORDER  BY embedding <=> :query_vector2::vector')
                    && str_contains($sql, 'LIMIT  :top_k');
            }))
            ->willReturn($stmt);

        $store = new PgVectorStore($pdo, 'my_table', 2);
        $results = $store->query([0.1, 0.2], 3, ['category' => 'tech']);

        // Assert query bindings
        $this->assertCount(4, $bindings);
        $this->assertSame(':query_vector', $bindings[0][0]);
        $this->assertSame('[0.1,0.2]', $bindings[0][1]);

        $this->assertSame(':query_vector2', $bindings[1][0]);
        $this->assertSame('[0.1,0.2]', $bindings[1][1]);

        $this->assertSame(':top_k', $bindings[2][0]);
        $this->assertSame(3, $bindings[2][1]);
        $this->assertSame(\PDO::PARAM_INT, $bindings[2][2]);

        $this->assertSame(':filter_category', $bindings[3][0]);
        $this->assertSame('tech', $bindings[3][1]);

        $this->assertCount(1, $results);
        $this->assertSame('doc-a', $results[0]['id']);
        $this->assertSame(0.85, $results[0]['score']);
        $this->assertSame(['category' => 'tech', 'content' => 'test'], $results[0]['metadata']);
    }

    public function testQueryThrowsOnFailure(): void
    {
        $pdo = $this->createMockPdo(true);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new \PDOException('Query execution error'));

        $store = new PgVectorStore($pdo, 'my_table', 2);

        $this->expectException(LLMeshException::class);
        $this->expectExceptionMessage('PgVectorStore: query failed:');
        $store->query([0.1, 0.2]);
    }
}
