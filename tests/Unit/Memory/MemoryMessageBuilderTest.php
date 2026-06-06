<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Memory;

use LLMesh\Core\Contracts\MemoryStoreInterface;
use LLMesh\Core\Memory\InMemoryStore;
use LLMesh\Core\Memory\MemoryMessageBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\Memory\MemoryMessageBuilder
 */
final class MemoryMessageBuilderTest extends TestCase
{
    private MemoryMessageBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new MemoryMessageBuilder();
    }

    // -------------------------------------------------------------------------
    // build()
    // -------------------------------------------------------------------------

    public function testBuildReturnsNewUserMessageWhenHistoryIsEmpty(): void
    {
        $store  = new InMemoryStore();
        $result = $this->builder->build('session', 'Hello!', $store);

        $this->assertCount(1, $result);
        $this->assertSame('user', $result[0]->role->value);
        $this->assertSame('Hello!', $result[0]->content);
    }

    public function testBuildPrefixesHistoryBeforeNewMessage(): void
    {
        $store = new InMemoryStore();
        $store->append('session', ['role' => 'user',      'content' => 'First',    'toolCallId' => null, 'toolName' => null]);
        $store->append('session', ['role' => 'assistant', 'content' => 'Response', 'toolCallId' => null, 'toolName' => null]);

        // We need a fresh store for build() to avoid double-counting the items
        // we just appended — in a real flow build() is called BEFORE append.
        // Use a separate store that has the history but will also receive the
        // new user message via build().
        $liveStore = new InMemoryStore();
        $liveStore->append('session', ['role' => 'user',      'content' => 'First',    'toolCallId' => null, 'toolName' => null]);
        $liveStore->append('session', ['role' => 'assistant', 'content' => 'Response', 'toolCallId' => null, 'toolName' => null]);

        $result = $this->builder->build('session', 'Follow-up', $liveStore);

        // history (2) + new user message (1) = 3
        $this->assertCount(3, $result);
        $this->assertSame('First', $result[0]->content);
        $this->assertSame('Response', $result[1]->content);
        $this->assertSame('Follow-up', $result[2]->content);
        $this->assertSame('user', $result[2]->role->value);
    }

    public function testBuildPersistsNewUserMessageInStore(): void
    {
        $store = new InMemoryStore();
        $this->builder->build('session', 'Hello!', $store);

        // After build(), the user message must be in the store for future calls
        $stored = $store->get('session');
        $this->assertCount(1, $stored);
        $this->assertSame('user', $stored[0]['role']);
        $this->assertSame('Hello!', $stored[0]['content']);
    }

    public function testBuildCallsStoreGetWithCorrectSessionId(): void
    {
        $mockStore = $this->createMock(MemoryStoreInterface::class);
        $mockStore
            ->expects($this->once())
            ->method('get')
            ->with('my-session')
            ->willReturn([]);

        // append is also called by build() — allow it without constraints
        $mockStore->method('append');

        $this->builder->build('my-session', 'Hi', $mockStore);
    }

    // -------------------------------------------------------------------------
    // save()
    // -------------------------------------------------------------------------

    public function testSavePersistsAssistantReplyWithCorrectRole(): void
    {
        $store = new InMemoryStore();
        $this->builder->save('session', 'I am an assistant', $store);

        $stored = $store->get('session');
        $this->assertCount(1, $stored);
        $this->assertSame('assistant', $stored[0]['role']);
        $this->assertSame('I am an assistant', $stored[0]['content']);
    }

    public function testSaveCallsStoreAppendWithCorrectArguments(): void
    {
        $capturedSessionId = null;
        $capturedMessage   = null;

        $mockStore = $this->createMock(MemoryStoreInterface::class);
        $mockStore
            ->expects($this->once())
            ->method('append')
            ->willReturnCallback(function (string $sid, array $msg) use (&$capturedSessionId, &$capturedMessage) {
                $capturedSessionId = $sid;
                $capturedMessage   = $msg;
            });

        $this->builder->save('sess-abc', 'Reply text', $mockStore);

        $this->assertSame('sess-abc', $capturedSessionId);
        $this->assertSame('assistant', $capturedMessage['role']);
        $this->assertSame('Reply text', $capturedMessage['content']);
    }

    // -------------------------------------------------------------------------
    // Integration: build → save cycle
    // -------------------------------------------------------------------------

    public function testFullBuildSaveCycleAccumulatesMessages(): void
    {
        $store = new InMemoryStore();

        // Turn 1
        $this->builder->build('sess', 'Question one?', $store);
        $this->builder->save('sess', 'Answer one.', $store);

        // Turn 2
        $messages = $this->builder->build('sess', 'Question two?', $store);

        // Messages at the start of Turn 2: [Q1, A1, Q2]
        $this->assertCount(3, $messages);
        $this->assertSame('Question one?', $messages[0]->content);
        $this->assertSame('Answer one.', $messages[1]->content);
        $this->assertSame('Question two?', $messages[2]->content);
    }
}
