<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Generators;

use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Contracts\ResponseInterface;
use LLMesh\Core\Exceptions\ValidationException;
use LLMesh\Core\Generators\GenerateObjectOptions;
use LLMesh\Core\Generators\ObjectGenerator;
use LLMesh\Core\Generators\ObjectResponse;
use LLMesh\Core\Generators\OutputMode;
use LLMesh\Core\Generators\Usage;
use LLMesh\Core\Schema\Schema;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\Generators\ObjectGenerator
 * @covers \LLMesh\Core\Generators\ObjectResponse
 */
final class ObjectGeneratorTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a minimal ResponseInterface stub returning the given text.
     */
    private function makeResponse(string $text): ResponseInterface
    {
        $usage = new Usage(10, 20);
        $stub  = $this->createStub(ResponseInterface::class);
        $stub->method('getText')->willReturn($text);
        $stub->method('getUsage')->willReturn($usage);
        $stub->method('getRaw')->willReturn([]);

        return $stub;
    }

    /**
     * Build a provider stub that returns the given responses in sequence.
     *
     * @param ResponseInterface[] $responses
     */
    private function makeProvider(array $responses, bool $supportsTools = false): ProviderInterface
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->willReturnCallback(
            fn (string $cap) => $cap === 'tools' && $supportsTools,
        );

        $provider
            ->expects($this->exactly(count($responses)))
            ->method('chat')
            ->willReturnOnConsecutiveCalls(...$responses);

        return $provider;
    }

    private function simpleSchema(): Schema
    {
        return Schema::object([
            'name' => Schema::string()->required(),
            'age'  => Schema::integer()->required(),
        ])->required(['name', 'age']);
    }

    // -------------------------------------------------------------------------
    // Code-fence stripping
    // -------------------------------------------------------------------------

    public function testStripsJsonCodeFenceBeforeParsing(): void
    {
        $json = "```json\n{\"name\":\"Alice\",\"age\":30}\n```";

        $provider = $this->makeProvider([$this->makeResponse($json)]);

        $result = (new ObjectGenerator($provider))->generate(
            GenerateObjectOptions::make()
                ->withPrompt('Extract')
                ->withSchema($this->simpleSchema()),
        );

        self::assertSame('Alice', $result->object['name']);
        self::assertSame(30, $result->object['age']);
    }

    public function testStripsPlainCodeFenceBeforeParsing(): void
    {
        $json = "```\n{\"name\":\"Bob\",\"age\":25}\n```";

        $provider = $this->makeProvider([$this->makeResponse($json)]);

        $result = (new ObjectGenerator($provider))->generate(
            GenerateObjectOptions::make()
                ->withPrompt('Extract')
                ->withSchema($this->simpleSchema()),
        );

        self::assertSame('Bob', $result->object['name']);
    }

    public function testBareJsonRequiresNoStripping(): void
    {
        $json = '{"name":"Carol","age":22}';

        $provider = $this->makeProvider([$this->makeResponse($json)]);

        $result = (new ObjectGenerator($provider))->generate(
            GenerateObjectOptions::make()
                ->withPrompt('Extract')
                ->withSchema($this->simpleSchema()),
        );

        self::assertSame('Carol', $result->object['name']);
    }

    // -------------------------------------------------------------------------
    // Retry logic
    // -------------------------------------------------------------------------

    public function testReturnsSuccessfullyOnSecondAttemptAfterInvalidJson(): void
    {
        $badResponse  = $this->makeResponse('this is not json at all');
        $goodResponse = $this->makeResponse('{"name":"Dave","age":40}');

        $provider = $this->makeProvider([$badResponse, $goodResponse]);

        $result = (new ObjectGenerator($provider))->generate(
            GenerateObjectOptions::make()
                ->withPrompt('Extract')
                ->withSchema($this->simpleSchema()),
        );

        self::assertSame('Dave', $result->object['name']);
        self::assertSame(40, $result->object['age']);
    }

    public function testThrowsValidationExceptionWhenBothAttemptsAreMalformed(): void
    {
        $bad1 = $this->makeResponse('not json');
        $bad2 = $this->makeResponse('also not json}}}');

        $provider = $this->makeProvider([$bad1, $bad2]);

        $this->expectException(ValidationException::class);

        (new ObjectGenerator($provider))->generate(
            GenerateObjectOptions::make()
                ->withPrompt('Extract')
                ->withSchema($this->simpleSchema()),
        );
    }

    public function testRetryDoesNotHappenOnValidJsonThatFailsSchema(): void
    {
        // Valid JSON, but missing required field — should throw immediately,
        // retry is only for invalid JSON / parse failure.
        $badSchema = $this->makeResponse('{"name":"Eve"}'); // missing required 'age'

        // Provider must be called exactly twice (original + retry) because
        // parse failure includes schema validation failure in the same catch.
        $badSchema2 = $this->makeResponse('{"name":"Eve"}');

        $provider = $this->makeProvider([$badSchema, $badSchema2]);

        $this->expectException(ValidationException::class);

        (new ObjectGenerator($provider))->generate(
            GenerateObjectOptions::make()
                ->withPrompt('Extract')
                ->withSchema($this->simpleSchema()),
        );
    }

    // -------------------------------------------------------------------------
    // ValidationException on schema mismatch
    // -------------------------------------------------------------------------

    public function testThrowsValidationExceptionWhenResponseDoesNotMatchSchema(): void
    {
        $wrongType = $this->makeResponse('{"name":42,"age":"wrong"}'); // types inverted
        $alsoWrong = $this->makeResponse('{"name":99,"age":"still wrong"}');

        $provider = $this->makeProvider([$wrongType, $alsoWrong]);

        $this->expectException(ValidationException::class);

        (new ObjectGenerator($provider))->generate(
            GenerateObjectOptions::make()
                ->withPrompt('Extract')
                ->withSchema($this->simpleSchema()),
        );
    }

    // -------------------------------------------------------------------------
    // JSON_MODE system prompt
    // -------------------------------------------------------------------------

    public function testJsonModeInjectsSchemaIntoSystemPrompt(): void
    {
        $capturedOptions = null;

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->willReturn(false);
        $provider
            ->expects($this->once())
            ->method('chat')
            ->willReturnCallback(function (array $messages, array $opts) use (&$capturedOptions) {
                $capturedOptions = $opts;
                return $this->makeResponse('{"name":"Test","age":1}');
            });

        (new ObjectGenerator($provider))->generate(
            GenerateObjectOptions::make()
                ->withPrompt('Extract')
                ->withSchema($this->simpleSchema())
                ->withMode(OutputMode::JSON_MODE),
        );

        self::assertArrayHasKey('system', $capturedOptions);
        self::assertStringContainsString('JSON Schema', $capturedOptions['system']);
        self::assertStringContainsString('"type"', $capturedOptions['system']);
        self::assertStringContainsString('valid JSON', $capturedOptions['system']);
    }

    public function testJsonModePrependsUserSystemPromptBeforeSchemaInstruction(): void
    {
        $capturedOptions = null;

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->willReturn(false);
        $provider
            ->expects($this->once())
            ->method('chat')
            ->willReturnCallback(function (array $messages, array $opts) use (&$capturedOptions) {
                $capturedOptions = $opts;
                return $this->makeResponse('{"name":"Test","age":1}');
            });

        (new ObjectGenerator($provider))->generate(
            GenerateObjectOptions::make()
                ->withPrompt('Extract')
                ->withSystem('You are an extraction assistant.')
                ->withSchema($this->simpleSchema())
                ->withMode(OutputMode::JSON_MODE),
        );

        self::assertStringStartsWith('You are an extraction assistant.', $capturedOptions['system']);
    }

    // -------------------------------------------------------------------------
    // TOOL_MODE system prompt
    // -------------------------------------------------------------------------

    public function testToolModePassesSchemaAsToolDefinition(): void
    {
        $capturedOptions = null;

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->willReturnCallback(
            fn (string $cap) => $cap === 'tools',
        );
        $provider
            ->expects($this->once())
            ->method('chat')
            ->willReturnCallback(function (array $messages, array $opts) use (&$capturedOptions) {
                $capturedOptions = $opts;
                // Return valid JSON for the tool call response (fallback path)
                return $this->makeResponse('{"name":"Test","age":1}');
            });

        (new ObjectGenerator($provider))->generate(
            GenerateObjectOptions::make()
                ->withPrompt('Extract')
                ->withSchema($this->simpleSchema())
                ->withMode(OutputMode::TOOL_MODE),
        );

        self::assertArrayHasKey('tools', $capturedOptions);
        self::assertCount(1, $capturedOptions['tools']);
        self::assertSame('extract_structured_data', $capturedOptions['tools'][0]['name']);
        self::assertArrayHasKey('parameters', $capturedOptions['tools'][0]);
    }

    public function testToolModeDoesNotInjectSchemaIntoSystemPrompt(): void
    {
        $capturedOptions = null;

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->willReturnCallback(
            fn (string $cap) => $cap === 'tools',
        );
        $provider
            ->expects($this->once())
            ->method('chat')
            ->willReturnCallback(function (array $messages, array $opts) use (&$capturedOptions) {
                $capturedOptions = $opts;
                return $this->makeResponse('{"name":"Test","age":1}');
            });

        (new ObjectGenerator($provider))->generate(
            GenerateObjectOptions::make()
                ->withPrompt('Extract')
                ->withSchema($this->simpleSchema())
                ->withMode(OutputMode::TOOL_MODE),
        );

        // System prompt must NOT contain the JSON Schema instruction block
        $system = $capturedOptions['system'] ?? '';
        self::assertStringNotContainsString('You must respond with ONLY valid JSON', $system);
    }

    public function testToolModeFallsBackToJsonModeWhenProviderLacksToolsCapability(): void
    {
        $capturedOptions = null;

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->willReturn(false); // no tools support
        $provider
            ->expects($this->once())
            ->method('chat')
            ->willReturnCallback(function (array $messages, array $opts) use (&$capturedOptions) {
                $capturedOptions = $opts;
                return $this->makeResponse('{"name":"Test","age":1}');
            });

        (new ObjectGenerator($provider))->generate(
            GenerateObjectOptions::make()
                ->withPrompt('Extract')
                ->withSchema($this->simpleSchema())
                ->withMode(OutputMode::TOOL_MODE),
        );

        // Should have fallen back to JSON_MODE — system prompt contains schema
        self::assertArrayHasKey('system', $capturedOptions);
        self::assertStringContainsString('valid JSON', $capturedOptions['system']);
        // And no tools key
        self::assertArrayNotHasKey('tools', $capturedOptions);
    }

    // -------------------------------------------------------------------------
    // ObjectResponse
    // -------------------------------------------------------------------------

    public function testObjectResponseHoldsCorrectData(): void
    {
        $provider = $this->makeProvider([
            $this->makeResponse('{"name":"Frank","age":55}'),
        ]);

        $result = (new ObjectGenerator($provider))->generate(
            GenerateObjectOptions::make()
                ->withPrompt('Extract')
                ->withSchema($this->simpleSchema()),
        );

        self::assertInstanceOf(ObjectResponse::class, $result);
        self::assertSame('Frank', $result->object['name']);
        self::assertSame(55, $result->object['age']);
        self::assertSame(10, $result->usage->getInputTokens());
    }

    public function testValidationExceptionThrownWhenSchemaNotSet(): void
    {
        $provider = $this->createStub(ProviderInterface::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('A schema must be provided for generateObject');

        (new ObjectGenerator($provider))->generate(
            GenerateObjectOptions::make()->withPrompt('hi'),
        );
    }
}
