# LLMesh — AI Agent Build Prompts

> Use these prompts sequentially, one per session with your AI agent.  
> Review and commit each output before starting the next prompt.  
> Never run two prompts in the same session.

---

## PROMPT 0 — Repo Scaffold & composer.json

```
You are a senior PHP open-source library author.

Scaffold the base repository structure for an open-source PHP library called LLMesh.

**Package identity:**
- Composer package: `llmesh/core`
- PHP namespace: `LLMesh\Core`
- PHP version: >= 8.1
- License: MIT

**Create the following directory structure:**
```
llmesh/
├── src/
│   ├── Contracts/
│   ├── Providers/
│   ├── Generators/
│   ├── Tools/
│   ├── Agents/
│   ├── Memory/
│   ├── Embeddings/
│   ├── RAG/
│   ├── Exceptions/
│   ├── Events/
│   └── Schema/
├── tests/
│   └── Unit/
├── .github/
│   └── workflows/
│       └── tests.yml
├── composer.json
├── phpunit.xml
├── .gitignore
├── CHANGELOG.md
├── CONTRIBUTING.md
├── LICENSE
└── README.md
```

**composer.json requirements:**
- PSR-4 autoloading for `LLMesh\Core` → `src/`
- PSR-4 autoloading for `LLMesh\Core\Tests` → `tests/`
- Require: PHP ^8.1, guzzlehttp/guzzle ^7.0, psr/http-client ^1.0, psr/log ^3.0, psr/event-dispatcher ^1.0
- Require-dev: phpunit/phpunit ^10.0, pestphp/pest ^2.0, mockery/mockery ^1.6
- Scripts: `test`, `test:coverage`, `lint`

**Additional files:**
- `phpunit.xml` configured for the `tests/` directory with coverage for `src/`
- `.github/workflows/tests.yml` that runs PHPUnit on PHP 8.1, 8.2, and 8.3
- `.gitignore` appropriate for a PHP library (vendor, coverage, .env)
- `CONTRIBUTING.md` with sections: local setup, coding standards, PR checklist
- `README.md` with just the title, one-line description, install command, and a "documentation coming soon" placeholder
- `LICENSE` with MIT license text

**Standards:**
- No implementation code yet — only structure and config files
- All files must be valid and immediately usable
- Follow PSR-4, PSR-12 coding standards conventions in config
```

---

## PROMPT 1 — Contracts (Interfaces)

```
You are a senior PHP open-source library author working on LLMesh (package: llmesh/core, namespace: LLMesh\Core).

The repository scaffold already exists. Your task is to define all core contracts (interfaces) for the library.

**Create the following interfaces inside `src/Contracts/`:**

1. `ProviderInterface`
   - `chat(array $messages, array $options = []): ResponseInterface`
   - `stream(array $messages, array $options = []): StreamInterface`
   - `embed(string|array $input, array $options = []): EmbeddingResponseInterface`
   - `supports(string $capability): bool` — checks if provider supports 'streaming', 'tools', 'embeddings'

2. `ResponseInterface`
   - `getText(): string`
   - `getUsage(): UsageInterface`
   - `getFinishReason(): string`
   - `getRaw(): array` — raw provider response

3. `StreamInterface` (extends \Iterator)
   - `getChunks(): \Generator`
   - `toSSE(): void` — writes SSE-formatted output to stdout
   - `getUsage(): UsageInterface` — available after stream completes

4. `EmbeddingResponseInterface`
   - `getEmbedding(): array` — float[]
   - `getDimensions(): int`
   - `getUsage(): UsageInterface`

5. `UsageInterface`
   - `getInputTokens(): int`
   - `getOutputTokens(): int`
   - `getTotalTokens(): int`
   - `getEstimatedCost(): ?float`

6. `MemoryStoreInterface`
   - `append(string $sessionId, array $message): void`
   - `get(string $sessionId): array`
   - `clear(string $sessionId): void`
   - `exists(string $sessionId): bool`

7. `VectorStoreInterface`
   - `upsert(string $id, array $vector, array $metadata = []): void`
   - `query(array $vector, int $topK = 5, array $filter = []): array`
   - `delete(string $id): void`

8. `ToolInterface`
   - `getName(): string`
   - `getDescription(): string`
   - `getParameterSchema(): array`
   - `execute(array $params): mixed`

**Also create DTOs in `src/Contracts/` or `src/Data/`:**
- `Message` — `role` (enum: user/assistant/system/tool), `content` (string), `toolCallId` (?string), `toolName` (?string)
- `ToolCall` — `id`, `name`, `arguments` (array)
- `ChunkDelta` — `text` (?string), `toolCall` (?ToolCall), `finishReason` (?string)

**Standards:**
- PHP 8.1+ features: enums for roles, readonly properties on DTOs, union types, named arguments
- All interfaces must have full PHPDoc blocks with @param, @return, @throws
- No implementation — interfaces and DTOs only
- Write a Unit test for each DTO to assert construction and property access
```

---

## PROMPT 2 — HTTP Client Abstraction & Config

```
You are a senior PHP open-source library author working on LLMesh (package: llmesh/core, namespace: LLMesh\Core).

Contracts from the previous step are already defined in `src/Contracts/`. Your task is to build the HTTP client abstraction and the library configuration layer.

**1. HTTP Client Abstraction (`src/Http/`)**

Create `HttpClient` — a PSR-18 compliant wrapper around any PSR-18 HTTP client:
- Constructor accepts `ClientInterface $client` (PSR-18), `RequestFactoryInterface`, `StreamFactoryInterface` (PSR-17)
- `post(string $url, array $payload, array $headers = []): array` — sends JSON POST, returns decoded array
- `stream(string $url, array $payload, array $headers = []): \Generator` — sends POST with `Accept: text/event-stream`, yields raw SSE lines as strings
- `setBaseUrl(string $url): self`
- `setTimeout(int $seconds): self`
- `withHeader(string $key, string $value): self` — fluent header injection
- Throws `LLMesh\Core\Exceptions\HttpException` on non-2xx responses with status code and body
- Throws `LLMesh\Core\Exceptions\ConnectionException` on network failures

Create `HttpClientFactory`:
- `static make(): self` — auto-discovers Guzzle if available, falls back to any installed PSR-18 client
- Returns a configured `HttpClient` instance

**2. Configuration (`src/Config/`)**

Create `LLMeshConfig`:
- Holds global config: default provider, timeout, retry attempts, retry delay
- Static `fromArray(array $config): self`
- `get(string $key, mixed $default = null): mixed`
- Config keys: `default_provider`, `timeout` (default: 30), `retry_attempts` (default: 3), `retry_delay_ms` (default: 500), `log_requests` (default: false)

Create `ProviderConfig`:
- Holds per-provider config: `api_key`, `base_url`, `model`, `max_tokens`, `temperature`, extra options
- `fromArray(array $config): self`
- All properties readonly

**3. Exceptions (`src/Exceptions/`)**

Create the full exception hierarchy:
- `LLMeshException` (base, extends \RuntimeException)
- `HttpException` extends LLMeshException — adds `statusCode()`, `responseBody()`
- `ConnectionException` extends LLMeshException
- `ProviderException` extends LLMeshException — adds `provider()` string
- `RateLimitException` extends ProviderException — adds `retryAfter(): ?int`
- `TokenLimitException` extends ProviderException — adds `limit(): int`, `used(): int`
- `ToolExecutionException` extends LLMeshException — adds `toolName(): string`
- `ValidationException` extends LLMeshException — adds `errors(): array`

**4. Retry Logic (`src/Http/RetryHandler.php`)**

Create `RetryHandler`:
- Wraps an `HttpClient` call in a retry loop
- Retries on `RateLimitException` (respects `retryAfter`) and `ConnectionException`
- Exponential backoff with jitter
- Configurable max attempts
- Does NOT retry on `TokenLimitException`, `ValidationException`, or 4xx errors other than 429

**Standards:**
- Full PHPDoc on all public methods
- Unit tests for: HttpException, config parsing, retry backoff logic, exception hierarchy
- No provider-specific code — this is pure infrastructure
```

---

## PROMPT 3 — Text Generation (`generateText`)

```
You are a senior PHP open-source library author working on LLMesh (package: llmesh/core, namespace: LLMesh\Core).

Contracts, HTTP client, config, and exceptions are already in place. Your task is to implement the `generateText` feature.

**1. Response DTO (`src/Generators/`)**

Create `TextResponse` implementing `LLMesh\Core\Contracts\ResponseInterface`:
- Readonly properties: `text`, `usage` (UsageInterface), `finishReason`, `raw` (array)
- `static fromProviderResponse(array $raw, callable $parser): self` — parser is provider-injected

Create `Usage` implementing `UsageInterface`:
- Readonly: `inputTokens`, `outputTokens`, `totalTokens`, `estimatedCost` (?float)
- `static fromArray(array $data): self`

**2. Text Generator (`src/Generators/TextGenerator.php`)**

Create `TextGenerator`:
- Constructor: `ProviderInterface $provider`
- `generate(GenerateTextOptions $options): TextResponse`
- Internally builds the messages array, dispatches to `$provider->chat()`, returns `TextResponse`

Create `GenerateTextOptions`:
- Readonly properties with defaults: `prompt` (?string), `messages` (array = []), `system` (?string), `temperature` (?float), `maxTokens` (?int), `stopSequences` (array = []), `tools` (array = []), `memory` (?MemoryStoreInterface), `sessionId` (?string)
- `static make(): self` with fluent builder methods: `withPrompt()`, `withMessages()`, `withSystem()`, `withTemperature()`, `withMaxTokens()`, `withMemory()`, `withTools()`
- Validates that at least one of `prompt` or `messages` is set; throws `ValidationException` otherwise
- If `memory` + `sessionId` are set: loads history from memory, appends new user message, saves assistant reply back after generation

**3. Main Entry Point (`src/LLMesh.php`)**

Create the `LLMesh` facade class (not Laravel-specific — plain static factory):
```php
LLMesh::generateText(
    provider: $provider,
    options: GenerateTextOptions::make()->withPrompt('...')->withMaxTokens(500)
);
```
- Static method `generateText(ProviderInterface $provider, GenerateTextOptions $options): TextResponse`
- For now this is the only method — others will be added in later steps

**4. PSR-14 Events**

Dispatch these events (create them in `src/Events/`):
- `GenerationStarted` — contains provider name, options snapshot
- `GenerationCompleted` — contains provider name, TextResponse, duration in ms
- `GenerationFailed` — contains provider name, exception

LLMesh accepts an optional PSR-14 `EventDispatcherInterface` via `LLMesh::withEventDispatcher($dispatcher)`.

**5. Unit Tests**

Write tests for:
- `GenerateTextOptions` validation (missing prompt AND messages throws)
- Memory injection: mock MemoryStoreInterface, assert `append` called before and after generation
- `TextGenerator::generate()` with a mocked provider: assert correct messages array is passed
- `LLMesh::generateText()` dispatches `GenerationStarted` and `GenerationCompleted` events

**Standards:**
- No real HTTP calls in tests — mock the ProviderInterface
- All options use PHP 8.1 readonly + named arguments
- PHPDoc on all public APIs
```

---

## PROMPT 4 — OpenAI Provider (`llmesh/openai`)

```
You are a senior PHP open-source library author.

You are building a SEPARATE Composer package: `llmesh/openai`.
It depends on `llmesh/core` and implements `LLMesh\Core\Contracts\ProviderInterface`.

**Package identity:**
- Composer package: `llmesh/openai`
- PHP namespace: `LLMesh\OpenAI`
- Requires: `llmesh/core: ^0.1`, PHP ^8.1

**Create `src/OpenAIProvider.php`** implementing `ProviderInterface`:

Constructor:
```php
public function __construct(
    private readonly string $apiKey,
    private readonly string $model = 'gpt-4o',
    private readonly ?HttpClient $httpClient = null,
    private readonly ?ProviderConfig $config = null,
)
```

Implement `chat(array $messages, array $options = []): ResponseInterface`:
- POST to `https://api.openai.com/v1/chat/completions`
- Map `$messages` (array of `Message` DTOs) to OpenAI format
- Map options: temperature, max_tokens, stop, tools (if present)
- Parse response into `TextResponse` via `TextResponse::fromProviderResponse()`
- Handle tool_calls in response: return them as part of raw, set finishReason to 'tool_calls'
- On 429: throw `RateLimitException` with `Retry-After` header value
- On 400: throw `ProviderException`
- On context length error: throw `TokenLimitException`

Implement `stream(array $messages, array $options = []): StreamInterface`:
- POST with `stream: true`
- Use `HttpClient::stream()` to get SSE lines
- Parse `data: {...}` lines, yield `ChunkDelta` objects
- Final `data: [DONE]` signals end of stream
- Return a `StreamResponse` implementing `StreamInterface`

Implement `embed(string|array $input, array $options = []): EmbeddingResponseInterface`:
- POST to `https://api.openai.com/v1/embeddings`
- Default model: `text-embedding-3-small`
- Return `EmbeddingResponse` with float[] vector

Implement `supports(string $capability): bool`:
- Returns true for: 'streaming', 'tools', 'embeddings'

Create `OpenAIModelEnum`:
- Cases: GPT4O, GPT4_TURBO, GPT35_TURBO, O1, O1_MINI, EMBEDDING_3_SMALL, EMBEDDING_3_LARGE
- `value` is the model string used in API calls
- `supportsTools(): bool`, `supportsStreaming(): bool`

**Unit Tests:**
- Mock HttpClient: assert correct request shape for chat, stream, embed
- Assert `RateLimitException` thrown on 429 with correct retryAfter
- Assert `TokenLimitException` thrown on context length error
- Assert tool_calls response sets finishReason correctly
- Test `supports()` returns correct booleans

**Standards:**
- Never hardcode the API key
- All request construction isolated in private methods
- OpenAI-specific parsing logic must not leak into llmesh/core
```

---

## PROMPT 5 — Anthropic Provider (`llmesh/anthropic`)

```
You are a senior PHP open-source library author.

You are building a SEPARATE Composer package: `llmesh/anthropic`.
It depends on `llmesh/core` and implements `LLMesh\Core\Contracts\ProviderInterface`.

**Package identity:**
- Composer package: `llmesh/anthropic`
- PHP namespace: `LLMesh\Anthropic`
- Requires: `llmesh/core: ^0.1`, PHP ^8.1

**Create `src/AnthropicProvider.php`** implementing `ProviderInterface`:

Constructor:
```php
public function __construct(
    private readonly string $apiKey,
    private readonly string $model = 'claude-sonnet-4-5',
    private readonly string $apiVersion = '2023-06-01',
    private readonly ?HttpClient $httpClient = null,
)
```

Key differences from OpenAI to implement correctly:
- Auth header is `x-api-key: {key}` NOT `Authorization: Bearer`
- Required header: `anthropic-version: 2023-06-01`
- System prompt is a TOP-LEVEL field, NOT a message in the array
- Messages array must alternate user/assistant — validate and throw `ValidationException` if not
- Tool definition format is different: `input_schema` instead of `parameters`
- Tool use in response: content block type `tool_use` with `id`, `name`, `input`
- Tool result message format: role `user`, content type `tool_result` with `tool_use_id`
- Streaming: events are `content_block_delta` with `delta.type: text_delta` or `input_json_delta`
- Stop reasons: `end_turn`, `max_tokens`, `stop_sequence`, `tool_use`

Implement `chat()`, `stream()`, `embed()` (Anthropic does NOT support embeddings — throw `\BadMethodCallException`), `supports()`.

Create `AnthropicModelEnum`:
- Cases: CLAUDE_SONNET_45, CLAUDE_OPUS_45, CLAUDE_HAIKU_35
- `supportsTools(): bool`, `supportsStreaming(): bool`

Create `src/MessageMapper.php`:
- Handles the conversion from `Message[]` DTOs → Anthropic API format
- Extracts system message separately
- Validates message alternation

**Unit Tests:**
- Assert system prompt extracted correctly and not included in messages array
- Assert `x-api-key` header used (not Bearer)
- Assert `ValidationException` on non-alternating messages
- Assert `BadMethodCallException` on `embed()`
- Assert tool_use content block parsed into ToolCall DTO correctly
- Assert streaming yields ChunkDelta with correct text on text_delta events

**Standards:**
- MessageMapper must be independently unit-testable
- No shared parsing code between OpenAI and Anthropic providers
```

---

## PROMPT 6 — Stream Support (`streamText`)

```
You are a senior PHP open-source library author working on LLMesh (package: llmesh/core, namespace: LLMesh\Core).

generateText, OpenAI provider, and Anthropic provider are already implemented. Your task is to implement the `streamText` feature in the core package.

**1. StreamResponse (`src/Generators/StreamResponse.php`)**

Implement `StreamInterface`:
- Constructor: accepts a `\Generator` that yields `ChunkDelta` objects
- `getChunks(): \Generator` — yields `ChunkDelta` objects one by one
- `toSSE(): void`:
  - Sets headers: `Content-Type: text/event-stream`, `Cache-Control: no-cache`, `X-Accel-Buffering: no`
  - For each chunk: outputs `data: {json}\n\n`, calls `flush()`
  - On stream end: outputs `data: [DONE]\n\n`
- `getUsage(): UsageInterface` — only available after generator is exhausted; throws `\LogicException` if called mid-stream
- `pipe(\Closure $callback): void` — calls callback with each ChunkDelta, useful for custom output handling
- `toText(): string` — consumes entire stream, concatenates all text deltas, returns full string

**2. Stream Generator (`src/Generators/StreamGenerator.php`)**

Create `StreamGenerator`:
- Constructor: `ProviderInterface $provider`
- `stream(GenerateTextOptions $options): StreamResponse`
- Validates provider supports streaming via `$provider->supports('streaming')`, throws `\RuntimeException` if not
- Calls `$provider->stream()`, wraps result in `StreamResponse`

**3. Add `streamText` to `LLMesh.php`**

```php
LLMesh::streamText(
    provider: $provider,
    options: GenerateTextOptions::make()->withPrompt('...')
): StreamResponse
```

**4. PSR-14 Events**

Add new events in `src/Events/`:
- `StreamStarted` — provider name, options snapshot
- `StreamChunkReceived` — ChunkDelta, chunk index
- `StreamCompleted` — provider name, total chunks, duration ms
- `StreamFailed` — provider name, exception

**5. Unit Tests:**
- `StreamResponse::toText()` concatenates all text deltas correctly
- `StreamResponse::getUsage()` throws `\LogicException` before stream is exhausted
- `StreamGenerator::stream()` throws `\RuntimeException` for provider that returns false for `supports('streaming')`
- Mock provider yields 3 ChunkDeltas: assert `toText()` returns concatenated text
- `toSSE()` output format: assert correct `data:` prefix and `[DONE]` termination (use output buffering in test)

**Standards:**
- `toSSE()` must never buffer the full response — stream chunk by chunk
- Generator must be lazy — no eager loading of chunks
- All tests use mocked providers, no real HTTP
```

---

## PROMPT 7 — Structured Object Generation (`generateObject`)

```
You are a senior PHP open-source library author working on LLMesh (package: llmesh/core, namespace: LLMesh\Core).

generateText and streamText are already implemented. Your task is to implement `generateObject`.

**1. Schema Builder (`src/Schema/`)**

Create `Schema` — a fluent builder that produces JSON Schema arrays:

```php
Schema::object([
    'name'   => Schema::string()->required()->minLength(1),
    'age'    => Schema::integer()->minimum(0)->maximum(120),
    'email'  => Schema::string()->format('email'),
    'tags'   => Schema::array(Schema::string()),
    'status' => Schema::enum(['active', 'inactive']),
    'meta'   => Schema::object(['key' => Schema::string()]),
])->required(['name', 'age'])
```

- `Schema::string()`, `Schema::integer()`, `Schema::number()`, `Schema::boolean()`, `Schema::array($itemSchema)`, `Schema::object(array $properties)`, `Schema::enum(array $values)`
- Chainable modifiers: `required()`, `nullable()`, `description(string $desc)`, `default(mixed $val)`, `minLength()`, `maxLength()`, `minimum()`, `maximum()`, `format()`
- `toArray(): array` — returns valid JSON Schema array
- `toJson(): string` — returns JSON string for API calls

**2. Object Response (`src/Generators/ObjectResponse.php`)**

Create `ObjectResponse`:
- Readonly: `object` (mixed — the parsed PHP object/array), `usage` (UsageInterface), `raw` (array)
- `static fromJson(string $json, SchemaInterface $schema): self` — parses and validates JSON against schema
- Throws `ValidationException` if response doesn't match schema

**3. Object Generator (`src/Generators/ObjectGenerator.php`)**

Create `ObjectGenerator`:
- Injects the schema into the system prompt as JSON Schema instructions
- Instructs the model to respond ONLY with valid JSON
- Calls `$provider->chat()` with the augmented prompt
- Strips any markdown code fences from response (models often wrap JSON in ```json)
- Parses and validates response into `ObjectResponse`
- Retries once if JSON is malformed (with a "your previous response was invalid JSON, try again" follow-up message)

Create `GenerateObjectOptions` (extends or mirrors `GenerateTextOptions`):
- All same fields as `GenerateTextOptions`
- Additional: `schema` (SchemaInterface, required)
- Additional: `mode` (enum: JSON_MODE, TOOL_MODE) — JSON_MODE uses system prompt injection, TOOL_MODE uses native structured output/tool calling if provider supports it

**4. Add `generateObject` to `LLMesh.php`**

```php
LLMesh::generateObject(
    provider: $provider,
    options: GenerateObjectOptions::make()
        ->withPrompt('Extract invoice data from: ...')
        ->withSchema(Schema::object([...]))
): ObjectResponse
```

**5. Unit Tests:**
- `Schema::toArray()` produces valid JSON Schema for all types
- `ObjectGenerator` strips ```json fences before parsing
- Retry logic: mock provider returns invalid JSON first, valid JSON second — assert object returned successfully
- `ValidationException` thrown when response doesn't match schema
- TOOL_MODE vs JSON_MODE: assert different system prompt construction

**Standards:**
- Schema builder must be independently usable without any provider
- No regex for JSON extraction — use `json_decode` with error checking only
- Schema validation should use the generated JSON Schema, not custom logic
```

---

## PROMPT 8 — Tool Calling

```
You are a senior PHP open-source library author working on LLMesh (package: llmesh/core, namespace: LLMesh\Core).

generateText, streamText, generateObject are implemented. Your task is to implement the Tool Calling system.

**1. Tool Builder (`src/Tools/Tool.php`)**

Create `Tool` implementing `ToolInterface`:

```php
$tool = Tool::make('get_weather')
    ->description('Get current weather for a city')
    ->parameters([
        'city' => Tool::string('City name')->required(),
        'unit' => Tool::enum(['celsius', 'fahrenheit'])->default('celsius'),
    ])
    ->handler(function (array $params): array {
        return ['temperature' => 28, 'condition' => 'sunny'];
    });
```

- `Tool::make(string $name): self`
- `description(string $desc): self`
- `parameters(array $params): self` — array of `ToolParameter` objects
- `handler(\Closure $fn): self`
- `execute(array $params): mixed` — calls the handler, wraps exceptions in `ToolExecutionException`
- `toArray(): array` — returns tool definition in OpenAI format (providers adapt from this canonical format)
- `getName(): string`, `getDescription(): string`, `getParameterSchema(): array`

Create `ToolParameter`:
- Types: string, integer, number, boolean, enum
- Chainable: `required()`, `description()`, `default()`, `enum(array $values)`, `minimum()`, `maximum()`
- `toSchemaArray(): array` — returns JSON Schema representation

**2. Tool Result DTO (`src/Tools/ToolResult.php`)**

Create `ToolResult`:
- Readonly: `toolCallId`, `toolName`, `result` (mixed), `isError` (bool)
- `static success(string $id, string $name, mixed $result): self`
- `static error(string $id, string $name, string $errorMessage): self`

**3. Tool Executor (`src/Tools/ToolExecutor.php`)**

Create `ToolExecutor`:
- `execute(ToolCall $toolCall, array $tools): ToolResult`
- Finds the matching tool by name, executes it with parsed arguments
- Catches all exceptions from tool handler, wraps in `ToolResult::error()`
- Supports parallel execution: `executeAll(array $toolCalls, array $tools): array`

**4. Multi-Step Tool Loop in `TextGenerator`**

Update `TextGenerator::generate()` to support multi-step tool use:
- After initial response, check if `finishReason === 'tool_calls'`
- Extract `ToolCall[]` from response
- Execute each via `ToolExecutor`
- Append assistant message (with tool_calls) and tool result messages to conversation
- Call provider again with updated messages
- Repeat until `finishReason !== 'tool_calls'` OR `maxSteps` reached
- `maxSteps` added to `GenerateTextOptions` (default: 5)

Update `GenerateTextOptions`:
- Add `maxSteps` (int, default: 5)
- Add `onToolCall(\Closure $callback): self` — optional callback fired before each tool execution (useful for logging)

**5. Unit Tests:**
- `Tool::execute()` wraps thrown exceptions in `ToolExecutionException`
- `ToolExecutor::executeAll()` returns correct ToolResult for each ToolCall
- Multi-step loop: mock provider returns tool_calls on step 1, text on step 2 — assert 2 provider calls made
- `maxSteps` respected: mock always returns tool_calls — assert loop stops at maxSteps
- `onToolCall` callback fired with correct ToolCall argument

**Standards:**
- Tool handler exceptions must never bubble up uncaught — always wrap in ToolResult::error()
- Tool execution must be synchronous in core; parallel execution handled via array iteration
- Tool parameter validation before execution: throw ValidationException on missing required params
```

---

## PROMPT 9 — Conversation Memory

```
You are a senior PHP open-source library author working on LLMesh (package: llmesh/core, namespace: LLMesh\Core).

All generators and tool calling are implemented. Your task is to implement the pluggable Conversation Memory system.

**1. InMemoryStore (`src/Memory/InMemoryStore.php`)**

Implements `MemoryStoreInterface`:
- Stores messages in a private array keyed by `sessionId`
- `append(string $sessionId, array $message): void`
- `get(string $sessionId): array` — returns [] if session not found
- `clear(string $sessionId): void`
- `exists(string $sessionId): bool`
- Thread-safe for single-process use (no special locking needed)

**2. RedisStore (`src/Memory/RedisStore.php`)**

Implements `MemoryStoreInterface`:
- Constructor: `\Redis|\Predis\Client $redis, string $prefix = 'llmesh:memory:', int $ttl = 3600`
- Stores messages as JSON-encoded array under key `{prefix}{sessionId}`
- `append()`: JSONGET + add message + JSONSET (or GET/SET with JSON encode/decode if RedisJSON not available)
- `clear()`: DEL the key
- TTL refreshed on every `append()`
- Gracefully handle Redis connection failure: throw `LLMeshException` with clear message

**3. DatabaseStore (`src/Memory/DatabaseStore.php`)**

Implements `MemoryStoreInterface`:
- Constructor: `\PDO $pdo, string $table = 'llmesh_memory'`
- `createTable(): void` — creates the table if not exists (call manually or via migration)
- Schema: `session_id VARCHAR(255)`, `message_index INT`, `role VARCHAR(50)`, `content TEXT`, `metadata JSON`, `created_at TIMESTAMP`
- `append()`: INSERT with auto-incremented message_index
- `get()`: SELECT ordered by message_index, return as Message DTO array
- `clear()`: DELETE WHERE session_id = ?
- Works with MySQL, PostgreSQL, SQLite

**4. Memory-Aware Message Builder (`src/Memory/MemoryMessageBuilder.php`)**

Create `MemoryMessageBuilder`:
- `build(string $sessionId, string $newUserMessage, MemoryStoreInterface $store): array`
  - Fetches history from store
  - Appends new user message
  - Returns full messages array ready for provider
- `save(string $sessionId, string $assistantReply, MemoryStoreInterface $store): void`
  - Saves assistant reply to store after generation

This class is called internally by `TextGenerator` when memory options are set.

**5. Unit Tests:**
- `InMemoryStore`: append, get, clear, exists — full coverage
- `InMemoryStore`: multiple sessions don't interfere with each other
- `RedisStore`: mock \Redis, assert correct key format and TTL set on append
- `DatabaseStore`: use SQLite in-memory PDO for tests — full append/get/clear cycle
- `MemoryMessageBuilder`: assert new message appended to history, assert save called with correct arguments
- `TextGenerator`: when memory + sessionId set, assert builder called before and after generation

**Standards:**
- `RedisStore` must not require a specific Redis client library — accept both ext-redis and predis via duck typing
- `DatabaseStore` table creation is opt-in (not automatic) to avoid surprise migrations
- All stores must handle empty/non-existent sessions gracefully (return [], not null or exception)
```

---

## PROMPT 10 — Agent Loop

```
You are a senior PHP open-source library author working on LLMesh (package: llmesh/core, namespace: LLMesh\Core).

All generators, tool calling, and memory are implemented. Your task is to implement the Agent Loop.

**1. Agent (`src/Agents/Agent.php`)**

```php
$agent = Agent::make(
    provider: $provider,
    systemPrompt: 'You are a backend engineer assistant.',
    tools: [$searchTool, $codeReviewTool],
    maxSteps: 10,
);

$result = $agent->run('Analyze our API and suggest improvements.');
```

Create `Agent`:
- `static make(ProviderInterface $provider, string $systemPrompt, array $tools, int $maxSteps = 10): self`
- `withMemory(MemoryStoreInterface $store, string $sessionId): self`
- `withEventDispatcher(EventDispatcherInterface $dispatcher): self`
- `onStep(\Closure $callback): self` — called after each reasoning step with `AgentStep`
- `run(string $prompt): AgentResult`

**2. Agent Loop Logic**

The `run()` method must:
1. Build initial messages from system prompt + user prompt (+ memory if set)
2. Call provider with tools registered
3. If response has tool_calls: execute tools, append results, increment step counter
4. If response is text (end_turn / stop): return `AgentResult`
5. If `maxSteps` reached without final answer: return `AgentResult` with `stoppedEarly: true`
6. Dispatch PSR-14 events: `AgentStarted`, `AgentStepCompleted`, `AgentFinished`, `AgentFailed`

**3. Agent DTOs**

Create `AgentStep`:
- Readonly: `stepNumber`, `input` (messages array), `output` (ResponseInterface), `toolCalls` (ToolCall[]), `toolResults` (ToolResult[]), `durationMs`

Create `AgentResult`:
- Readonly: `finalText`, `steps` (AgentStep[]), `totalSteps`, `stoppedEarly` (bool), `usage` (aggregated UsageInterface across all steps)
- `getStepCount(): int`
- `getTotalCost(): ?float`
- `toArray(): array` — full serializable result for logging

**4. PSR-14 Events (`src/Events/`)**

- `AgentStarted` — provider, system prompt, tool names, max steps
- `AgentStepCompleted` — AgentStep DTO
- `AgentToolCalled` — ToolCall, ToolResult, step number
- `AgentFinished` — AgentResult
- `AgentFailed` — exception, steps completed so far

**5. Unit Tests:**
- Agent completes in 1 step (no tools needed): assert `totalSteps === 1`, `stoppedEarly === false`
- Agent uses tool then answers: mock provider returns tool_call on step 1, text on step 2 — assert `totalSteps === 2`
- `maxSteps` enforcement: mock always returns tool_calls — assert `stoppedEarly === true` after maxSteps
- `onStep` callback fired for each step with correct `AgentStep`
- Aggregated usage: assert `AgentResult::usage` sums tokens across all steps
- `AgentFailed` event dispatched when provider throws exception

**Standards:**
- Agent loop must be deterministic — same inputs always produce same execution path
- Never swallow exceptions silently — always dispatch `AgentFailed` then rethrow
- `AgentResult` must be fully serializable to array/JSON for audit logging
- No infinite loops possible — maxSteps is a hard ceiling enforced before provider call
```

---

## PROMPT 11 — Embeddings

```
You are a senior PHP open-source library author working on LLMesh (package: llmesh/core, namespace: LLMesh\Core).

All core generators and the agent loop are implemented. Your task is to implement the Embeddings feature.

**1. EmbeddingResponse (`src/Embeddings/EmbeddingResponse.php`)**

Implements `EmbeddingResponseInterface`:
- Readonly: `embedding` (float[]), `dimensions` (int), `usage` (UsageInterface), `model` (string)
- `static fromArray(array $data, callable $parser): self`
- `cosineSimilarity(EmbeddingResponse $other): float` — compute cosine similarity between two embedding vectors
- `toArray(): float[]`

**2. Embedding Generator (`src/Embeddings/EmbeddingGenerator.php`)**

Create `EmbeddingGenerator`:
- `embed(ProviderInterface $provider, string $input, array $options = []): EmbeddingResponse`
- `embedBatch(ProviderInterface $provider, array $inputs, array $options = []): EmbeddingResponse[]`
  - For providers that support batch: single API call
  - For providers that don't: parallel or sequential individual calls
  - Returns array indexed same as input

**3. Add to `LLMesh.php`**

```php
LLMesh::embed(provider: $provider, input: 'text'): EmbeddingResponse
LLMesh::embedBatch(provider: $provider, inputs: ['a', 'b', 'c']): array
```

**4. Update OpenAI provider (`llmesh/openai`)**

Update `OpenAIProvider::embed()`:
- Support batch input (array of strings → single API call)
- Parse response correctly for batch (multiple embedding objects in response)
- Supported models: `text-embedding-3-small`, `text-embedding-3-large`, `text-embedding-ada-002`

**5. Unit Tests:**
- `EmbeddingResponse::cosineSimilarity()`: test with known vectors (orthogonal = 0.0, identical = 1.0)
- `EmbeddingGenerator::embedBatch()`: mock provider, assert correct number of responses returned
- Batch with provider that doesn't support batch: assert multiple individual calls made
- OpenAI batch: mock HTTP, assert single API call for array input, correct index mapping

**Standards:**
- `cosineSimilarity` must handle zero vectors gracefully (return 0.0, not divide by zero)
- Batch embedding indices must always correspond to input indices — never reorder
```

---

## PROMPT 12 — RAG Pipeline

```
You are a senior PHP open-source library author working on LLMesh (package: llmesh/core, namespace: LLMesh\Core).

Embeddings are implemented. Your task is to implement the RAG (Retrieval-Augmented Generation) pipeline.

**1. Document Model (`src/RAG/Document.php`)**

Create `Document`:
- Readonly: `id` (string, auto-generated UUID if not provided), `content` (string), `metadata` (array), `embedding` (?float[])
- `withEmbedding(float[] $embedding): self` — returns new instance (immutable)
- `static fromFile(string $path): self`
- `static fromText(string $content, array $metadata = []): self`

**2. Loaders (`src/RAG/Loaders/`)**

Create `LoaderInterface`:
- `load(): Document[]`

Implement:
- `TextLoader(string $path)` — loads plain text file as single document
- `DirectoryLoader(string $path, array $extensions = ['txt', 'md'])` — loads all matching files in directory
- `ArrayLoader(array $texts)` — wraps raw strings as Documents

**3. Splitters (`src/RAG/Splitters/`)**

Create `SplitterInterface`:
- `split(Document $document): Document[]`

Implement:
- `RecursiveCharacterSplitter(int $chunkSize = 512, int $overlap = 50)` — splits on paragraphs, then sentences, then words
- `SentenceSplitter(int $maxSentences = 5)` — splits by sentence boundaries

**4. Vector Stores (`src/RAG/VectorStores/`)**

Create `VectorStoreInterface` (already in Contracts — implement it here):
- `InMemoryVectorStore` — stores vectors in array, cosine similarity search
- `PgVectorStore(\PDO $pdo, string $table = 'llmesh_vectors')`:
  - `createTable(): void` — CREATE TABLE with `vector(dimensions)` column using pgvector extension
  - `upsert()`, `query()`, `delete()`
  - Query uses `<=>` operator (cosine distance) for nearest neighbor search

**5. RAG Pipeline (`src/RAG/Pipeline.php`)**

```php
$pipeline = Pipeline::make()
    ->load(new DirectoryLoader('/docs'))
    ->split(new RecursiveCharacterSplitter(chunkSize: 512, overlap: 50))
    ->embed($embeddingProvider)
    ->store(new PgVectorStore($pdo));

$pipeline->run(); // ingest

$chunks = $pipeline->retrieve($userQuestion, topK: 5); // returns Document[]
```

Create `Pipeline`:
- `static make(): self`
- `load(LoaderInterface $loader): self`
- `split(SplitterInterface $splitter): self`
- `embed(ProviderInterface $provider): self` — uses `EmbeddingGenerator` internally
- `store(VectorStoreInterface $store): self`
- `run(): PipelineResult` — executes load→split→embed→store, returns stats
- `retrieve(string $query, int $topK = 5): Document[]` — embed query, search vector store, return Documents
- `onProgress(\Closure $callback): self` — called with `(int $current, int $total)` during ingestion

Create `PipelineResult`:
- Readonly: `documentsLoaded`, `chunksCreated`, `chunksStored`, `durationMs`, `totalTokensUsed`

**6. Add to `LLMesh.php`**

```php
LLMesh::pipeline(): Pipeline  // returns new Pipeline instance
```

**7. Unit Tests:**
- `RecursiveCharacterSplitter`: assert chunk size respected, overlap correct
- `InMemoryVectorStore`: upsert + query returns topK most similar vectors
- `PgVectorStore`: use SQLite mock or assert correct SQL generated
- `Pipeline::run()` calls loader → splitter → embed → store in order (mock all components)
- `Pipeline::retrieve()` embeds query and calls store::query (assert correct topK passed)
- `PipelineResult` stats correct: assert documentsLoaded and chunksCreated counts

**Standards:**
- Pipeline must be resumable: if `run()` interrupted, re-running should not duplicate vectors (upsert by document id)
- InMemoryVectorStore is for dev/testing only — document this clearly
- PgVectorStore requires pgvector extension — check on construction and throw clear error if unavailable
```

---

## PROMPT 13 — Laravel Adapter (`llmesh/laravel`)

```
You are a senior PHP open-source library author.

You are building a SEPARATE Composer package: `llmesh/laravel`.
It is a first-class Laravel integration for LLMesh core.

**Package identity:**
- Composer package: `llmesh/laravel`
- PHP namespace: `LLMesh\Laravel`
- Requires: `llmesh/core: ^0.1`, `laravel/framework: ^10.0|^11.0`, PHP ^8.1

**1. Service Provider (`src/LLMeshServiceProvider.php`)**

- Auto-discovered via `extra.laravel.providers` in composer.json
- Registers all providers from config as singletons in the container
- Binds `LLMesh\Core\Contracts\ProviderInterface` to the configured default provider
- `boot()`: publishes config, registers Artisan commands

**2. Config (`config/llmesh.php`)**

```php
return [
    'default' => env('LLMESH_PROVIDER', 'openai'),
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model'   => env('OPENAI_MODEL', 'gpt-4o'),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model'   => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
        ],
        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
            'model'   => env('GROQ_MODEL', 'llama3-8b-8192'),
        ],
    ],
    'memory' => [
        'driver'  => env('LLMESH_MEMORY_DRIVER', 'database'),
        'ttl'     => 3600,
    ],
    'retry' => [
        'attempts' => 3,
        'delay_ms' => 500,
    ],
];
```

**3. Facade (`src/Facades/LLMesh.php`)**

Laravel Facade proxying to the `LLMesh\Core\LLMesh` static class:
```php
use LLMesh\Laravel\Facades\LLMesh;

LLMesh::generateText(options: GenerateTextOptions::make()->withPrompt('Hello'));
LLMesh::streamText(...);
LLMesh::generateObject(...);
LLMesh::embed(...);
```

**4. Eloquent Memory Store (`src/Memory/EloquentMemoryStore.php`)**

Implements `MemoryStoreInterface` using Eloquent:
- Uses a `LlmeshMemory` Eloquent model
- Migration published via service provider: `create_llmesh_memory_table`
- Schema: `session_id`, `role`, `content`, `metadata` (JSON), `message_index`, `timestamps`

**5. Artisan Commands (`src/Commands/`)**

- `php artisan llmesh:test` — sends a test prompt to the configured default provider and prints the response
- `php artisan llmesh:providers` — lists all configured providers with connection status
- `php artisan llmesh:clear-memory {sessionId}` — clears memory for a session

**6. Queue Support**

Create `RunAgentJob` (implements `ShouldQueue`):
- Constructor: `AgentOptions $options, string $sessionId`
- Dispatches result via a `AgentCompleted` Laravel event when done
- Supports `onQueue()`, `onConnection()`

**7. Unit/Feature Tests:**
- Service provider registers default provider correctly
- Facade resolves from container
- Config published correctly
- `EloquentMemoryStore`: full append/get/clear cycle using SQLite in-memory
- `llmesh:test` command outputs response text

**Standards:**
- Never assume specific provider packages are installed — check with `class_exists()` before binding
- Config must be publishable: `php artisan vendor:publish --tag=llmesh-config`
- Migration must be publishable: `php artisan vendor:publish --tag=llmesh-migrations`
- All provider resolution lazy — never instantiate a provider whose API key is missing
```

---

## PROMPT 14 — Observability & Cost Tracking

```
You are a senior PHP open-source library author working on LLMesh (package: llmesh/core, namespace: LLMesh\Core).

All features are implemented. Your task is to add production-grade observability across the entire library.

**1. Cost Calculator (`src/Observability/CostCalculator.php`)**

Create `CostCalculator`:
- Static pricing table for known models (input $/1M tokens, output $/1M tokens):
  - gpt-4o: $2.50 / $10.00
  - gpt-4-turbo: $10.00 / $30.00
  - claude-sonnet-4-5: $3.00 / $15.00
  - text-embedding-3-small: $0.02 / $0.00
  - (and others)
- `calculate(string $model, int $inputTokens, int $outputTokens): float`
- `isKnownModel(string $model): bool`
- Pricing table must be updatable: `CostCalculator::setPricing(string $model, float $inputPer1M, float $outputPer1M): void`

Update `Usage` DTO to call `CostCalculator` when constructing `estimatedCost`.

**2. Request Logger (`src/Observability/RequestLogger.php`)**

Implements PSR-3 `LoggerAwareInterface`:
- Logs (at DEBUG level): provider, model, input token count, output token count, estimated cost, duration ms
- Logs (at WARNING level): RateLimitException with retryAfter
- Logs (at ERROR level): all other exceptions with provider + message
- Structured log format (JSON-serializable array): `{provider, model, tokens_in, tokens_out, cost_usd, duration_ms, status}`

**3. Middleware Pattern (`src/Observability/`)**

Create a `MiddlewareStack` that wraps provider calls:
- `LoggingMiddleware` — wraps provider, logs before/after via PSR-3
- `RetryMiddleware` — wraps provider, handles retry logic (move from HttpClient to here)
- `CostTrackingMiddleware` — accumulates cost across multiple calls in a session

Usage:
```php
$provider = MiddlewareStack::wrap($rawProvider)
    ->with(new LoggingMiddleware($logger))
    ->with(new RetryMiddleware(attempts: 3))
    ->with(new CostTrackingMiddleware($tracker));
```

**4. Usage Tracker (`src/Observability/UsageTracker.php`)**

Create `UsageTracker`:
- Accumulates `Usage` objects across a session
- `record(UsageInterface $usage): void`
- `getTotalInputTokens(): int`
- `getTotalOutputTokens(): int`
- `getTotalCost(): float`
- `getSummary(): array`
- `reset(): void`

**5. Unit Tests:**
- `CostCalculator::calculate()` for known models returns correct float
- `CostCalculator` with unknown model returns `null` (not exception)
- `LoggingMiddleware`: mock PSR-3 logger, assert DEBUG log called with correct keys
- `RetryMiddleware`: assert provider called N times on RateLimitException
- `UsageTracker`: accumulate 3 usages, assert correct totals
- `MiddlewareStack` executes middleware in correct order (LIFO wrapping)

**Standards:**
- Cost calculations must never throw — return null if model unknown
- Logging must never block or throw — catch all logger exceptions internally
- Middleware pattern must not change the public interface of ProviderInterface
```

---

## PROMPT 15 — Final: Tests, Docs & Release Prep

```
You are a senior PHP open-source library author working on LLMesh (package: llmesh/core, namespace: LLMesh\Core).

All features are implemented. Your task is to bring the project to open-source release quality.

**1. Test Coverage Audit**

Run PHPUnit with coverage and identify any gaps. Write missing tests to reach:
- Minimum 90% line coverage on `src/`
- 100% coverage on all Contracts, DTOs, and Schema builder
- Integration test: full pipeline using InMemoryVectorStore + mocked embedding provider

**2. README.md (final)**

Write a complete README with:
- Badges: PHP version, License, Tests passing, Packagist version
- One-paragraph description
- Installation: `composer require llmesh/core llmesh/openai`
- Quick start (5-line example using generateText)
- Feature table (same as spec differentiator table)
- Links to docs for each feature: generateText, streamText, generateObject, tools, agents, memory, RAG
- Contributing section pointing to CONTRIBUTING.md

**3. CHANGELOG.md**

Write v0.1.0 entry following Keep a Changelog format:
- Added: list all implemented features
- No Changed or Removed sections for initial release

**4. CONTRIBUTING.md (final)**

Complete the contributing guide:
- Local setup: `git clone`, `composer install`, `vendor/bin/pest`
- Coding standards: PSR-12, must pass `phpcs`
- How to add a new provider: step-by-step with checklist
- How to add a vector store adapter: step-by-step
- PR checklist: tests pass, PHPDoc added, CHANGELOG updated, no debug code
- Issue templates: bug report, feature request

**5. GitHub Actions (final)**

Update `.github/workflows/tests.yml`:
- Matrix: PHP 8.1, 8.2, 8.3
- Steps: checkout, setup PHP, composer install, phpcs lint, pest tests with coverage
- Coverage upload to Codecov
- Add separate `release.yml` workflow triggered on tag push that publishes to Packagist via webhook

**6. composer.json (final)**

Audit and finalize:
- Correct `suggest` section for optional dependencies (Redis, PDO drivers, pgvector)
- `funding` section pointing to GitHub Sponsors
- `keywords`: php, ai, llm, openai, anthropic, sdk, machine-learning
- Verify all `require` and `require-dev` versions are correct and pinned appropriately

**Standards:**
- README examples must be copy-paste runnable (no pseudocode)
- All PHPDoc @throws tags must be accurate — audit every public method
- No TODO comments in shipped code — either implement or remove
- Version in composer.json should be omitted (managed by git tags + Packagist)
```

---

## Execution Order Summary

| Prompt | Feature | Package |
|--------|---------|---------|
| 0 | Repo scaffold + composer.json | `llmesh/core` |
| 1 | Contracts + DTOs | `llmesh/core` |
| 2 | HTTP client + Config + Exceptions | `llmesh/core` |
| 3 | `generateText` | `llmesh/core` |
| 4 | OpenAI provider | `llmesh/openai` |
| 5 | Anthropic provider | `llmesh/anthropic` |
| 6 | `streamText` | `llmesh/core` |
| 7 | `generateObject` + Schema | `llmesh/core` |
| 8 | Tool calling | `llmesh/core` |
| 9 | Conversation memory | `llmesh/core` |
| 10 | Agent loop | `llmesh/core` |
| 11 | Embeddings | `llmesh/core` |
| 12 | RAG pipeline | `llmesh/core` |
| 13 | Laravel adapter | `llmesh/laravel` |
| 14 | Observability + cost tracking | `llmesh/core` |
| 15 | Tests, docs, release prep | all |

> **Rule:** Commit and review each prompt's output before starting the next.  
> If the agent drifts or hallucinates, paste the relevant interface/contract from the earlier step as grounding context.
