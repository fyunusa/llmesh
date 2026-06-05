<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Tools;

use LLMesh\Core\Data\ToolCall;
use LLMesh\Core\Exceptions\ToolExecutionException;
use LLMesh\Core\Exceptions\ValidationException;
use LLMesh\Core\Tools\Tool;
use LLMesh\Core\Tools\ToolParameter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\Tools\Tool
 * @covers \LLMesh\Core\Tools\ToolParameter
 */
final class ToolTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Builder / fluent API
    // -------------------------------------------------------------------------

    public function testMakeSetName(): void
    {
        $tool = Tool::make('get_weather');
        self::assertSame('get_weather', $tool->getName());
    }

    public function testDescriptionIsSet(): void
    {
        $tool = Tool::make('search')->description('Search the web');
        self::assertSame('Search the web', $tool->getDescription());
    }

    public function testGetParameterSchemaHasRequiredFields(): void
    {
        $tool = Tool::make('weather')
            ->parameters([
                'city' => Tool::string('City name')->required(),
                'unit' => Tool::enum(['celsius', 'fahrenheit'])->default('celsius'),
            ]);

        $schema = $tool->getParameterSchema();

        self::assertSame('object', $schema['type']);
        self::assertArrayHasKey('properties', $schema);
        self::assertArrayHasKey('city', $schema['properties']);
        self::assertArrayHasKey('unit', $schema['properties']);
        self::assertSame(['city'], $schema['required']); // unit is not required
    }

    public function testGetParameterSchemaNoRequiredWhenNoneMarked(): void
    {
        $tool = Tool::make('foo')->parameters([
            'bar' => Tool::string(),
        ]);

        $schema = $tool->getParameterSchema();
        self::assertArrayNotHasKey('required', $schema);
    }

    // -------------------------------------------------------------------------
    // toArray() — OpenAI canonical format
    // -------------------------------------------------------------------------

    public function testToArrayProducesOpenAIFormat(): void
    {
        $tool = Tool::make('get_weather')
            ->description('Get weather')
            ->parameters([
                'city' => Tool::string('City name')->required(),
            ]);

        $def = $tool->toArray();

        self::assertSame('function', $def['type']);
        self::assertSame('get_weather', $def['function']['name']);
        self::assertSame('Get weather', $def['function']['description']);
        self::assertArrayHasKey('parameters', $def['function']);
        self::assertSame('object', $def['function']['parameters']['type']);
        self::assertArrayHasKey('city', $def['function']['parameters']['properties']);
    }

    // -------------------------------------------------------------------------
    // ToolParameter types
    // -------------------------------------------------------------------------

    public function testStringParameterType(): void
    {
        self::assertSame('string', ToolParameter::string()->toSchemaArray()['type']);
    }

    public function testIntegerParameterType(): void
    {
        self::assertSame('integer', ToolParameter::integer()->toSchemaArray()['type']);
    }

    public function testNumberParameterType(): void
    {
        self::assertSame('number', ToolParameter::number()->toSchemaArray()['type']);
    }

    public function testBooleanParameterType(): void
    {
        self::assertSame('boolean', ToolParameter::boolean()->toSchemaArray()['type']);
    }

    public function testEnumParameterAllStrings(): void
    {
        $schema = ToolParameter::enum(['a', 'b'])->toSchemaArray();
        self::assertSame('string', $schema['type']);
        self::assertSame(['a', 'b'], $schema['enum']);
    }

    public function testParameterDescriptionSet(): void
    {
        $schema = ToolParameter::string('A description')->toSchemaArray();
        self::assertSame('A description', $schema['description']);
    }

    public function testParameterDescriptionChainable(): void
    {
        $schema = ToolParameter::integer()->description('The count')->toSchemaArray();
        self::assertSame('The count', $schema['description']);
    }

    public function testParameterDefault(): void
    {
        $schema = ToolParameter::string()->default('hello')->toSchemaArray();
        self::assertSame('hello', $schema['default']);
    }

    public function testParameterMinimum(): void
    {
        $schema = ToolParameter::integer()->minimum(0)->toSchemaArray();
        self::assertSame(0, $schema['minimum']);
    }

    public function testParameterMaximum(): void
    {
        $schema = ToolParameter::number()->maximum(100.0)->toSchemaArray();
        self::assertSame(100.0, $schema['maximum']);
    }

    // -------------------------------------------------------------------------
    // execute() — success path
    // -------------------------------------------------------------------------

    public function testExecuteCallsHandler(): void
    {
        $called = false;
        $tool = Tool::make('ping')
            ->handler(function (array $params) use (&$called) {
                $called = true;
                return 'pong';
            });

        $result = $tool->execute([]);
        self::assertTrue($called);
        self::assertSame('pong', $result);
    }

    public function testExecutePassesParamsToHandler(): void
    {
        $receivedParams = null;
        $tool = Tool::make('echo')
            ->parameters(['msg' => Tool::string()])
            ->handler(function (array $params) use (&$receivedParams) {
                $receivedParams = $params;
                return $params['msg'];
            });

        $tool->execute(['msg' => 'hello']);
        self::assertSame(['msg' => 'hello'], $receivedParams);
    }

    // -------------------------------------------------------------------------
    // execute() — exception wrapping
    // -------------------------------------------------------------------------

    public function testExecuteWrapsExceptionInToolExecutionException(): void
    {
        $tool = Tool::make('broken')
            ->handler(function (array $params): never {
                throw new \RuntimeException('Something went wrong');
            });

        $this->expectException(ToolExecutionException::class);
        $this->expectExceptionMessage('broken');

        $tool->execute([]);
    }

    public function testExecuteWrapsExceptionPreservesPreviousThrowable(): void
    {
        $original = new \InvalidArgumentException('original error');
        $tool = Tool::make('broken')
            ->handler(function () use ($original): never {
                throw $original;
            });

        try {
            $tool->execute([]);
            self::fail('Expected ToolExecutionException');
        } catch (ToolExecutionException $e) {
            self::assertSame($original, $e->getPrevious());
        }
    }

    public function testExecuteToolExecutionExceptionNotDoubleWrapped(): void
    {
        // If handler throws ToolExecutionException directly it must not be re-wrapped
        $original = new ToolExecutionException('already wrapped', 'test');
        $tool = Tool::make('test')
            ->handler(function () use ($original): never {
                throw $original;
            });

        try {
            $tool->execute([]);
            self::fail('Expected ToolExecutionException');
        } catch (ToolExecutionException $e) {
            self::assertSame($original, $e);
        }
    }

    // -------------------------------------------------------------------------
    // execute() — validation
    // -------------------------------------------------------------------------

    public function testExecuteThrowsValidationExceptionForMissingRequired(): void
    {
        $tool = Tool::make('weather')
            ->parameters([
                'city' => Tool::string()->required(),
            ])
            ->handler(fn (array $p) => $p);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('city');

        $tool->execute([]); // missing 'city'
    }

    public function testExecuteDoesNotThrowWhenOptionalParamMissing(): void
    {
        $tool = Tool::make('weather')
            ->parameters([
                'city' => Tool::string()->required(),
                'unit' => Tool::string()->default('celsius'), // optional
            ])
            ->handler(fn (array $p) => $p['city']);

        $result = $tool->execute(['city' => 'London']); // no 'unit'
        self::assertSame('London', $result);
    }

    // -------------------------------------------------------------------------
    // No handler
    // -------------------------------------------------------------------------

    public function testExecuteWithoutHandlerThrowsBadMethodCallException(): void
    {
        $tool = Tool::make('empty');

        $this->expectException(\BadMethodCallException::class);

        $tool->execute([]);
    }
}
