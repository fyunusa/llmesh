<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Tools;

use LLMesh\Core\Data\ToolCall;
use LLMesh\Core\Tools\Tool;
use LLMesh\Core\Tools\ToolExecutor;
use LLMesh\Core\Tools\ToolResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\Tools\ToolExecutor
 * @covers \LLMesh\Core\Tools\ToolResult
 */
final class ToolExecutorTest extends TestCase
{
    // -------------------------------------------------------------------------
    // execute() — success
    // -------------------------------------------------------------------------

    public function testExecuteReturnsSuccessResult(): void
    {
        $tool = Tool::make('ping')
            ->handler(fn (array $p) => 'pong');

        $toolCall = new ToolCall('call-1', 'ping', []);
        $result   = (new ToolExecutor())->execute($toolCall, [$tool]);

        self::assertInstanceOf(ToolResult::class, $result);
        self::assertFalse($result->isError);
        self::assertSame('pong', $result->result);
        self::assertSame('call-1', $result->toolCallId);
        self::assertSame('ping', $result->toolName);
    }

    public function testExecutePassesArgumentsToTool(): void
    {
        $received = null;
        $tool = Tool::make('echo')
            ->parameters(['msg' => Tool::string()])
            ->handler(function (array $p) use (&$received) {
                $received = $p;
                return $p['msg'];
            });

        $toolCall = new ToolCall('call-2', 'echo', ['msg' => 'hello']);
        (new ToolExecutor())->execute($toolCall, [$tool]);

        self::assertSame(['msg' => 'hello'], $received);
    }

    // -------------------------------------------------------------------------
    // execute() — error paths
    // -------------------------------------------------------------------------

    public function testExecuteWrapsHandlerExceptionInErrorResult(): void
    {
        $tool = Tool::make('boom')
            ->handler(function (): never {
                throw new \RuntimeException('kaboom');
            });

        $toolCall = new ToolCall('call-3', 'boom', []);
        $result   = (new ToolExecutor())->execute($toolCall, [$tool]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('kaboom', (string) $result->result);
    }

    public function testExecuteReturnsErrorForUnknownToolName(): void
    {
        $tool = Tool::make('known')->handler(fn () => true);

        $toolCall = new ToolCall('call-4', 'unknown_tool', []);
        $result   = (new ToolExecutor())->execute($toolCall, [$tool]);

        self::assertTrue($result->isError);
        self::assertSame('call-4', $result->toolCallId);
        self::assertStringContainsString('unknown_tool', (string) $result->result);
    }

    public function testExecuteReturnsErrorForMissingRequiredParam(): void
    {
        $tool = Tool::make('weather')
            ->parameters(['city' => Tool::string()->required()])
            ->handler(fn (array $p) => $p);

        $toolCall = new ToolCall('call-5', 'weather', []); // missing 'city'
        $result   = (new ToolExecutor())->execute($toolCall, [$tool]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('city', (string) $result->result);
    }

    // -------------------------------------------------------------------------
    // executeAll()
    // -------------------------------------------------------------------------

    public function testExecuteAllReturnsOneResultPerCall(): void
    {
        $tools = [
            Tool::make('add')->handler(fn (array $p) => $p['a'] + $p['b']),
            Tool::make('mul')->handler(fn (array $p) => $p['a'] * $p['b']),
        ];

        $toolCalls = [
            new ToolCall('c1', 'add', ['a' => 2, 'b' => 3]),
            new ToolCall('c2', 'mul', ['a' => 4, 'b' => 5]),
        ];

        $results = (new ToolExecutor())->executeAll($toolCalls, $tools);

        self::assertCount(2, $results);

        self::assertFalse($results[0]->isError);
        self::assertSame(5, $results[0]->result);
        self::assertSame('c1', $results[0]->toolCallId);

        self::assertFalse($results[1]->isError);
        self::assertSame(20, $results[1]->result);
        self::assertSame('c2', $results[1]->toolCallId);
    }

    public function testExecuteAllIsolatesErrors(): void
    {
        // One tool fails, the other succeeds — results from both must be returned
        $tools = [
            Tool::make('ok')->handler(fn () => 'ok'),
            Tool::make('fail')->handler(function (): never {
                throw new \RuntimeException('error!');
            }),
        ];

        $toolCalls = [
            new ToolCall('c1', 'ok', []),
            new ToolCall('c2', 'fail', []),
        ];

        $results = (new ToolExecutor())->executeAll($toolCalls, $tools);

        self::assertCount(2, $results);
        self::assertFalse($results[0]->isError);
        self::assertTrue($results[1]->isError);
    }

    public function testExecuteAllPreservesOrder(): void
    {
        $tools = [
            Tool::make('a')->handler(fn () => 1),
            Tool::make('b')->handler(fn () => 2),
            Tool::make('c')->handler(fn () => 3),
        ];

        $toolCalls = [
            new ToolCall('x', 'c', []),
            new ToolCall('y', 'a', []),
            new ToolCall('z', 'b', []),
        ];

        $results = (new ToolExecutor())->executeAll($toolCalls, $tools);

        self::assertSame(3, $results[0]->result);
        self::assertSame(1, $results[1]->result);
        self::assertSame(2, $results[2]->result);
    }

    // -------------------------------------------------------------------------
    // ToolResult helpers
    // -------------------------------------------------------------------------

    public function testToolResultSuccessFactory(): void
    {
        $r = ToolResult::success('id1', 'my_tool', ['key' => 'val']);
        self::assertFalse($r->isError);
        self::assertSame('id1', $r->toolCallId);
        self::assertSame('my_tool', $r->toolName);
        self::assertSame(['key' => 'val'], $r->result);
    }

    public function testToolResultErrorFactory(): void
    {
        $r = ToolResult::error('id2', 'my_tool', 'Something broke');
        self::assertTrue($r->isError);
        self::assertSame('Something broke', $r->result);
    }

    public function testToolResultToStringScalar(): void
    {
        $r = ToolResult::success('x', 't', 'hello');
        self::assertSame('hello', $r->resultToString());
    }

    public function testToolResultToStringArray(): void
    {
        $r = ToolResult::success('x', 't', ['temp' => 28]);
        self::assertSame('{"temp":28}', $r->resultToString());
    }
}
