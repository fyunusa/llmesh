<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Memory;

use LLMesh\Core\Memory\RedisStore;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\Memory\RedisStore
 */
final class RedisStoreTest extends TestCase
{
    private FakeRedis $redis;
    private RedisStore $store;

    protected function setUp(): void
    {
        $this->redis = new FakeRedis();
        // @phpstan-ignore-next-line (duck-typed)
        $this->store = new RedisStore($this->redis, 'llmesh:memory:', 3600);
    }

    // -------------------------------------------------------------------------
    // Key format
    // -------------------------------------------------------------------------

    public function testAppendUsesCorrectKeyFormat(): void
    {
        $this->store->append('my-session', ['role' => 'user', 'content' => 'Hi', 'toolCallId' => null, 'toolName' => null]);

        $this->assertArrayHasKey('llmesh:memory:my-session', $this->redis->data);
    }

    // -------------------------------------------------------------------------
    // TTL is set on every append
    // -------------------------------------------------------------------------

    public function testAppendSetsCorrectTtl(): void
    {
        $this->store->append('session', ['role' => 'user', 'content' => 'X', 'toolCallId' => null, 'toolName' => null]);

        $this->assertSame(3600, $this->redis->ttls['llmesh:memory:session']);
    }

    // -------------------------------------------------------------------------
    // append merges with existing data
    // -------------------------------------------------------------------------

    public function testAppendMergesWithExistingMessages(): void
    {
        $existing = [['role' => 'user', 'content' => 'First', 'toolCallId' => null, 'toolName' => null]];
        $this->redis->data['llmesh:memory:session'] = json_encode($existing);

        $newMsg = ['role' => 'assistant', 'content' => 'Second', 'toolCallId' => null, 'toolName' => null];
        $this->store->append('session', $newMsg);

        $decoded = json_decode($this->redis->data['llmesh:memory:session'], true);
        $this->assertCount(2, $decoded);
        $this->assertSame('First', $decoded[0]['content']);
        $this->assertSame('Second', $decoded[1]['content']);
    }

    // -------------------------------------------------------------------------
    // get
    // -------------------------------------------------------------------------

    public function testGetReturnsEmptyArrayWhenKeyDoesNotExist(): void
    {
        $this->assertSame([], $this->store->get('nonexistent'));
    }

    public function testGetDecodesStoredJson(): void
    {
        $messages = [['role' => 'user', 'content' => 'Hello', 'toolCallId' => null, 'toolName' => null]];
        $this->redis->data['llmesh:memory:session'] = json_encode($messages);

        $result = $this->store->get('session');
        $this->assertCount(1, $result);
        $this->assertSame('Hello', $result[0]['content']);
    }

    // -------------------------------------------------------------------------
    // clear
    // -------------------------------------------------------------------------

    public function testClearDeletesKeyWithCorrectFormat(): void
    {
        $this->redis->data['llmesh:memory:my-session'] = json_encode([]);

        $this->store->clear('my-session');

        $this->assertArrayNotHasKey('llmesh:memory:my-session', $this->redis->data);
    }

    // -------------------------------------------------------------------------
    // exists
    // -------------------------------------------------------------------------

    public function testExistsReturnsTrueWhenKeyIsPresent(): void
    {
        $this->redis->data['llmesh:memory:session'] = json_encode([]);

        $this->assertTrue($this->store->exists('session'));
    }

    public function testExistsReturnsFalseWhenKeyIsAbsent(): void
    {
        $this->assertFalse($this->store->exists('session'));
    }

    // -------------------------------------------------------------------------
    // Custom prefix
    // -------------------------------------------------------------------------

    public function testCustomPrefixIsApplied(): void
    {
        $redis = new FakeRedis();
        // @phpstan-ignore-next-line
        $store = new RedisStore($redis, 'chat:', 600);
        $store->append('abc', ['role' => 'user', 'content' => 'Hi', 'toolCallId' => null, 'toolName' => null]);

        $this->assertArrayHasKey('chat:abc', $redis->data);
        $this->assertSame(600, $redis->ttls['chat:abc']);
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    public function testAppendWrapsRedisExceptionInLLMeshException(): void
    {
        $this->redis->throwOnGet = true;

        $this->expectException(\LLMesh\Core\Exceptions\LLMeshException::class);
        $this->expectExceptionMessageMatches('/Connection refused/');

        $this->store->append('session', ['role' => 'user', 'content' => 'X', 'toolCallId' => null, 'toolName' => null]);
    }
}
