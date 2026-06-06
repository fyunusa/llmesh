<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Memory;

use LLMesh\Core\Memory\DatabaseStore;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\Memory\DatabaseStore
 */
final class DatabaseStoreTest extends TestCase
{
    private \PDO $pdo;
    private DatabaseStore $store;

    protected function setUp(): void
    {
        // Use an in-memory SQLite database — no filesystem, no cleanup needed
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->store = new DatabaseStore($this->pdo);
        $this->store->createTable();
    }

    // -------------------------------------------------------------------------
    // get on empty session
    // -------------------------------------------------------------------------

    public function testGetReturnsEmptyArrayForUnknownSession(): void
    {
        $this->assertSame([], $this->store->get('unknown'));
    }

    // -------------------------------------------------------------------------
    // append / get round-trip
    // -------------------------------------------------------------------------

    public function testAppendAndGetSingleMessage(): void
    {
        $msg = ['role' => 'user', 'content' => 'Hello world', 'toolCallId' => null, 'toolName' => null];
        $this->store->append('sess-1', $msg);

        $result = $this->store->get('sess-1');
        $this->assertCount(1, $result);
        $this->assertSame('user', $result[0]['role']);
        $this->assertSame('Hello world', $result[0]['content']);
        $this->assertNull($result[0]['toolCallId']);
        $this->assertNull($result[0]['toolName']);
    }

    public function testAppendMultipleMessagesPreservesOrder(): void
    {
        $this->store->append('sess-1', ['role' => 'user',      'content' => 'Msg1', 'toolCallId' => null, 'toolName' => null]);
        $this->store->append('sess-1', ['role' => 'assistant', 'content' => 'Msg2', 'toolCallId' => null, 'toolName' => null]);
        $this->store->append('sess-1', ['role' => 'user',      'content' => 'Msg3', 'toolCallId' => null, 'toolName' => null]);

        $result = $this->store->get('sess-1');
        $this->assertCount(3, $result);
        $this->assertSame('Msg1', $result[0]['content']);
        $this->assertSame('Msg2', $result[1]['content']);
        $this->assertSame('Msg3', $result[2]['content']);
    }

    // -------------------------------------------------------------------------
    // Tool metadata round-trip
    // -------------------------------------------------------------------------

    public function testAppendToolMessagePreservesMetadata(): void
    {
        $msg = [
            'role'       => 'tool',
            'content'    => 'Result data',
            'toolCallId' => 'call-abc',
            'toolName'   => 'get_weather',
        ];
        $this->store->append('sess-1', $msg);

        $result = $this->store->get('sess-1');
        $this->assertCount(1, $result);
        $this->assertSame('call-abc', $result[0]['toolCallId']);
        $this->assertSame('get_weather', $result[0]['toolName']);
    }

    // -------------------------------------------------------------------------
    // clear
    // -------------------------------------------------------------------------

    public function testClearRemovesAllMessagesForSession(): void
    {
        $this->store->append('sess-1', ['role' => 'user', 'content' => 'X', 'toolCallId' => null, 'toolName' => null]);
        $this->store->clear('sess-1');

        $this->assertSame([], $this->store->get('sess-1'));
    }

    public function testClearDoesNotAffectOtherSessions(): void
    {
        $this->store->append('sess-1', ['role' => 'user', 'content' => 'A', 'toolCallId' => null, 'toolName' => null]);
        $this->store->append('sess-2', ['role' => 'user', 'content' => 'B', 'toolCallId' => null, 'toolName' => null]);

        $this->store->clear('sess-1');

        $this->assertSame([], $this->store->get('sess-1'));
        $this->assertCount(1, $this->store->get('sess-2'));
    }

    // -------------------------------------------------------------------------
    // exists
    // -------------------------------------------------------------------------

    public function testExistsReturnsFalseForUnknownSession(): void
    {
        $this->assertFalse($this->store->exists('unknown'));
    }

    public function testExistsReturnsTrueAfterAppend(): void
    {
        $this->store->append('sess-1', ['role' => 'user', 'content' => 'X', 'toolCallId' => null, 'toolName' => null]);
        $this->assertTrue($this->store->exists('sess-1'));
    }

    public function testExistsReturnsFalseAfterClear(): void
    {
        $this->store->append('sess-1', ['role' => 'user', 'content' => 'X', 'toolCallId' => null, 'toolName' => null]);
        $this->store->clear('sess-1');
        $this->assertFalse($this->store->exists('sess-1'));
    }

    // -------------------------------------------------------------------------
    // Custom table name
    // -------------------------------------------------------------------------

    public function testCustomTableNameIsUsed(): void
    {
        $pdo   = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $store = new DatabaseStore($pdo, 'custom_messages');
        $store->createTable();

        $store->append('s', ['role' => 'user', 'content' => 'Hi', 'toolCallId' => null, 'toolName' => null]);
        $this->assertCount(1, $store->get('s'));
    }

    // -------------------------------------------------------------------------
    // createTable is idempotent
    // -------------------------------------------------------------------------

    public function testCreateTableIsIdempotent(): void
    {
        // Calling twice must not throw
        $this->store->createTable();
        $this->addToAssertionCount(1);
    }
}
