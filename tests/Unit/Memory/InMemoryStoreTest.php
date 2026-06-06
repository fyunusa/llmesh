<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Memory;

use LLMesh\Core\Memory\InMemoryStore;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\Memory\InMemoryStore
 */
final class InMemoryStoreTest extends TestCase
{
    private InMemoryStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
    }

    // -------------------------------------------------------------------------
    // append / get
    // -------------------------------------------------------------------------

    public function testGetReturnsEmptyArrayForUnknownSession(): void
    {
        $this->assertSame([], $this->store->get('unknown-session'));
    }

    public function testAppendAndGetSingleMessage(): void
    {
        $msg = ['role' => 'user', 'content' => 'Hello', 'toolCallId' => null, 'toolName' => null];
        $this->store->append('sess-1', $msg);

        $this->assertSame([$msg], $this->store->get('sess-1'));
    }

    public function testAppendMultipleMessagesPreservesOrder(): void
    {
        $msg1 = ['role' => 'user',      'content' => 'Hi',    'toolCallId' => null, 'toolName' => null];
        $msg2 = ['role' => 'assistant', 'content' => 'Hello', 'toolCallId' => null, 'toolName' => null];
        $msg3 = ['role' => 'user',      'content' => 'Bye',   'toolCallId' => null, 'toolName' => null];

        $this->store->append('sess-1', $msg1);
        $this->store->append('sess-1', $msg2);
        $this->store->append('sess-1', $msg3);

        $this->assertSame([$msg1, $msg2, $msg3], $this->store->get('sess-1'));
    }

    // -------------------------------------------------------------------------
    // clear
    // -------------------------------------------------------------------------

    public function testClearRemovesAllMessagesForSession(): void
    {
        $msg = ['role' => 'user', 'content' => 'Hello', 'toolCallId' => null, 'toolName' => null];
        $this->store->append('sess-1', $msg);
        $this->store->clear('sess-1');

        $this->assertSame([], $this->store->get('sess-1'));
    }

    public function testClearNonExistentSessionDoesNotThrow(): void
    {
        $this->store->clear('does-not-exist');
        $this->addToAssertionCount(1); // reached without exception
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
    // Session isolation
    // -------------------------------------------------------------------------

    public function testMultipleSessionsDoNotInterfereWithEachOther(): void
    {
        $msgA = ['role' => 'user', 'content' => 'Session A', 'toolCallId' => null, 'toolName' => null];
        $msgB = ['role' => 'user', 'content' => 'Session B', 'toolCallId' => null, 'toolName' => null];

        $this->store->append('session-a', $msgA);
        $this->store->append('session-b', $msgB);

        $this->assertSame([$msgA], $this->store->get('session-a'));
        $this->assertSame([$msgB], $this->store->get('session-b'));
    }

    public function testClearingOneSessionLeavesOtherIntact(): void
    {
        $msgA = ['role' => 'user', 'content' => 'A', 'toolCallId' => null, 'toolName' => null];
        $msgB = ['role' => 'user', 'content' => 'B', 'toolCallId' => null, 'toolName' => null];

        $this->store->append('session-a', $msgA);
        $this->store->append('session-b', $msgB);
        $this->store->clear('session-a');

        $this->assertSame([], $this->store->get('session-a'));
        $this->assertSame([$msgB], $this->store->get('session-b'));
    }
}
