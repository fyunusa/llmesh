# LLMesh — Project Specification

> A framework-agnostic PHP toolkit for building AI-powered applications and agents.  
> Works with Laravel, CodeIgniter, Symfony, Slim, or plain PHP.

---

## Vision

The PHP ecosystem deserves a first-class AI SDK — not a thin wrapper around one provider's HTTP API, but a unified, composable toolkit that standardizes how PHP developers integrate LLMs regardless of the framework they use.

Inspired by Vercel's AI SDK for TypeScript, but designed with PHP idioms, Composer-first distribution, and PSR compliance at its core.

---

## Package Identity

| Field       | Value                          |
|-------------|-------------------------------|
| Package name | `llmesh/core`             |
| Namespace    | `LLMesh\Core`             |
| PHP version  | >= 8.1                       |
| License      | MIT                          |
| Distribution | Composer (packagist.org)     |

---

## Architecture Overview

```
llmesh/core (core)
│
├── Core Layer (framework-agnostic)
│   ├── Providers          # LLM provider abstraction
│   ├── Generators         # Text, Object, Stream generators
│   ├── Tools              # Tool/function calling
│   ├── Agents             # Agent loop orchestration
│   ├── Memory             # Context & conversation memory
│   ├── Embeddings         # Vector embedding interface
│   └── RAG                # Retrieval-augmented generation
│
├── Provider Packages (separate Composer packages)
│   ├── llmesh/openai
│   ├── llmesh/anthropic
│   ├── llmesh/gemini
│   ├── llmesh/groq
│   └── llmesh/mistral
│
└── Framework Adapters (optional, separate packages)
    ├── llmesh/laravel
    └── llmesh/symfony
```

---

## Core Module: LLMesh Core

The heart of the package. Framework-agnostic. Pure PHP 8.1+.

### 1. Provider Abstraction

A unified interface all provider packages must implement.

```php
interface ProviderInterface
{
    public function chat(array $messages, array $options = []): ResponseInterface;
    public function stream(array $messages, array $options = []): StreamInterface;
    public function embed(string|array $input, array $options = []): EmbeddingResponseInterface;
}
```

**Supported providers (via separate packages):**
- OpenAI (GPT-4o, GPT-4 Turbo, o1)
- Anthropic (Claude 3.5 Sonnet, Claude 3 Opus)
- Google Gemini (1.5 Pro, Flash)
- Groq (Llama 3, Mixtral)
- Mistral
- Ollama (local models)
- OpenRouter (multi-model gateway)

---

### 2. Text Generation (`generateText`)

```php
use LLMesh\Core\Facades\AI;
use LLMesh\Core\Providers\Anthropic;

$result = AI::generateText(
    model: new Anthropic('claude-sonnet-4-5'),
    prompt: 'Explain event sourcing in one paragraph.',
);

echo $result->text;
echo $result->usage->inputTokens;
echo $result->usage->outputTokens;
```

**Supports:**
- Single prompt
- Multi-turn message arrays
- System prompts
- Temperature, max tokens, stop sequences
- Token usage reporting

---

### 3. Structured Object Generation (`generateObject`)

Generate type-safe structured output validated against a schema.

```php
use LLMesh\Core\Schema\Schema;

$result = AI::generateObject(
    model: new OpenAI('gpt-4o'),
    prompt: 'Extract the invoice details from this text: ...',
    schema: Schema::object([
        'invoice_number' => Schema::string()->required(),
        'total_amount'   => Schema::number()->required(),
        'due_date'       => Schema::string()->format('date'),
        'line_items'     => Schema::array(Schema::object([
            'description' => Schema::string(),
            'amount'      => Schema::number(),
        ])),
    ]),
);

$invoice = $result->object; // fully typed, validated PHP object
```

---

### 4. Streaming (`streamText`)

Server-Sent Events (SSE) compatible streaming, works in any PHP context.

```php
$stream = AI::streamText(
    model: new OpenAI('gpt-4o'),
    prompt: 'Write a short story about a Lagos developer.',
);

foreach ($stream->chunks() as $chunk) {
    echo $chunk->text;
    flush();
}

// Or pipe directly to SSE response
$stream->toSSE(); // outputs Content-Type: text/event-stream headers + chunks
```

---

### 5. Tool Calling / Function Calling

Register callable PHP functions as tools the model can invoke.

```php
use LLMesh\Core\Tools\Tool;

$weatherTool = Tool::make('get_weather')
    ->description('Get current weather for a city')
    ->parameters([
        'city'    => Tool::string('City name')->required(),
        'unit'    => Tool::enum(['celsius', 'fahrenheit'])->default('celsius'),
    ])
    ->handler(function (array $params): array {
        // Call your weather API here
        return ['temperature' => 28, 'condition' => 'sunny'];
    });

$result = AI::generateText(
    model: new OpenAI('gpt-4o'),
    prompt: 'What is the weather in Abuja?',
    tools: [$weatherTool],
);
```

**Supports:**
- Multi-step tool use (model calls tool, result fed back, model continues)
- Parallel tool calls
- Tool result injection
- Max steps limit to prevent infinite loops

---

### 6. Agent Loop

Autonomous multi-step execution with tools.

```php
use LLMesh\Core\Agents\Agent;

$agent = Agent::make(
    model: new Anthropic('claude-sonnet-4-5'),
    systemPrompt: 'You are a backend engineer assistant.',
    tools: [$weatherTool, $searchTool, $codeExecutorTool],
    maxSteps: 10,
);

$result = $agent->run('Analyze our API latency and suggest optimizations.');

echo $result->finalText;
echo $result->steps; // number of reasoning steps taken
```

---

### 7. Conversation Memory

Pluggable memory backends for multi-turn conversations.

```php
use LLMesh\Core\Memory\InMemoryStore;
use LLMesh\Core\Memory\RedisStore;
use LLMesh\Core\Memory\DatabaseStore;

// Redis-backed (recommended for production)
$memory = new RedisStore(prefix: 'chat:user:42');

$result = AI::generateText(
    model: new OpenAI('gpt-4o'),
    prompt: $userMessage,
    memory: $memory,
    sessionId: 'user-42-session-1',
);
```

**Memory backends:**
- `InMemoryStore` — for tests and single-request use
- `RedisStore` — production-grade, TTL support
- `DatabaseStore` — PDO-based, works with any SQL database
- Custom: implement `MemoryStoreInterface`

---

### 8. Embeddings

```php
$result = AI::embed(
    model: new OpenAI('text-embedding-3-small'),
    input: 'The quick brown fox',
);

$vector = $result->embedding; // float[]
$dimensions = $result->dimensions;
```

Batch embedding:

```php
$results = AI::embedBatch(
    model: new OpenAI('text-embedding-3-small'),
    inputs: ['Document one', 'Document two', 'Document three'],
);
```

---

### 9. RAG (Retrieval-Augmented Generation)

```php
use LLMesh\Core\RAG\Pipeline;
use LLMesh\Core\RAG\Loaders\TextLoader;
use LLMesh\Core\RAG\Splitters\RecursiveCharacterSplitter;
use LLMesh\Core\RAG\VectorStores\PgVectorStore;

// Ingest
$pipeline = Pipeline::make()
    ->load(new TextLoader('/path/to/docs'))
    ->split(new RecursiveCharacterSplitter(chunkSize: 512, overlap: 50))
    ->embed(new OpenAI('text-embedding-3-small'))
    ->store(new PgVectorStore($pdo));

$pipeline->run();

// Query
$result = AI::generateText(
    model: new Anthropic('claude-sonnet-4-5'),
    prompt: $userQuestion,
    context: $pipeline->retrieve($userQuestion, topK: 5),
);
```

**Vector store adapters:**
- `PgVectorStore` (PostgreSQL + pgvector)
- `PineconeStore`
- `WeaviateStore`
- `ChromaStore`
- `InMemoryVectorStore` (for dev/testing)

---

## Framework Adapters

### Laravel Adapter (`llmesh/laravel`)

- Service Provider auto-discovery
- Config file (`config/ai.php`) for provider setup
- Facade: `AI::generateText(...)`
- Artisan commands: `php artisan ai:test`, `php artisan ai:embed`
- Queue-friendly: agent runs dispatchable as jobs
- Eloquent-based `DatabaseStore` for conversation memory
- Middleware for rate limiting AI endpoints

```php
// config/ai.php
return [
    'default' => env('AI_PROVIDER', 'openai'),
    'providers' => [
        'openai'    => ['api_key' => env('OPENAI_API_KEY')],
        'anthropic' => ['api_key' => env('ANTHROPIC_API_KEY')],
        'groq'      => ['api_key' => env('GROQ_API_KEY')],
    ],
];
```

### Symfony Adapter (`llmesh/symfony`)

- Bundle with DI container integration
- Service tags for tool auto-registration
- Config via `config/packages/ai_sdk.yaml`

---

## PSR Compliance

| Standard | Implementation |
|----------|---------------|
| PSR-4    | Autoloading |
| PSR-7    | HTTP message interfaces for streaming responses |
| PSR-11   | Container interface for DI |
| PSR-14   | Event dispatcher (hooks into generation lifecycle) |
| PSR-18   | HTTP client interface (swap Guzzle, Symfony HttpClient, etc.) |

---

## Error Handling

```php
use LLMesh\Core\Exceptions\ProviderException;
use LLMesh\Core\Exceptions\RateLimitException;
use LLMesh\Core\Exceptions\TokenLimitException;
use LLMesh\Core\Exceptions\ToolExecutionException;

try {
    $result = AI::generateText(...);
} catch (RateLimitException $e) {
    // $e->retryAfter — seconds until retry
} catch (TokenLimitException $e) {
    // prompt + context too large
} catch (ProviderException $e) {
    // upstream provider error with status code
}
```

Automatic retry with exponential backoff is configurable per provider.

---

## Observability

```php
// Lifecycle hooks via PSR-14 events
use LLMesh\Core\Events\GenerationStarted;
use LLMesh\Core\Events\GenerationCompleted;
use LLMesh\Core\Events\ToolCalled;

// Built-in logging middleware
AI::withLogger($psr3Logger)->generateText(...);

// Token usage tracking
$result->usage->inputTokens;
$result->usage->outputTokens;
$result->usage->totalCost; // estimated cost in USD
```

---

## Roadmap

### v0.1 — Foundation
- [ ] Core `generateText` with OpenAI + Anthropic providers
- [ ] `streamText` with SSE output
- [ ] PSR-18 HTTP client abstraction
- [ ] Basic error handling + retry logic
- [ ] Laravel adapter v1

### v0.2 — Structure + Tools
- [ ] `generateObject` with schema validation
- [ ] Tool calling with multi-step support
- [ ] `embed` + `embedBatch`
- [ ] InMemory + Redis memory stores

### v0.3 — Agents + RAG
- [ ] Agent loop with configurable max steps
- [ ] RAG pipeline (loader → splitter → embed → store → retrieve)
- [ ] PgVector + Pinecone vector store adapters
- [ ] DatabaseStore memory backend

### v0.4 — Ecosystem
- [ ] Groq, Gemini, Mistral, Ollama providers
- [ ] Symfony adapter
- [ ] Observability: cost tracking, token logging
- [ ] CLI tooling: `vendor/bin/ai`

### v1.0 — Stable
- [ ] Full test coverage (PHPUnit + Pest)
- [ ] Comprehensive documentation site
- [ ] Changelog + semantic versioning
- [ ] Community provider contribution guide

---

## Differentiators vs Existing PHP Libraries

| Feature                        | openai-php | LLPhant | Prism | **LLMesh** |
|-------------------------------|------------|---------|-------|--------------|
| Multi-provider abstraction     | ❌         | ✅      | ✅    | ✅           |
| Streaming (SSE-ready)          | ✅         | ⚠️      | ⚠️    | ✅           |
| Structured object generation   | ❌         | ❌      | ⚠️    | ✅           |
| Agent loop                     | ❌         | ✅      | ❌    | ✅           |
| RAG pipeline                   | ❌         | ✅      | ❌    | ✅           |
| PSR-18 HTTP client             | ❌         | ❌      | ❌    | ✅           |
| Framework-agnostic core        | ✅         | ❌      | ✅    | ✅           |
| Laravel first-class adapter    | ❌         | ❌      | ✅    | ✅           |
| Cost tracking                  | ❌         | ❌      | ❌    | ✅           |
| Pluggable memory backends      | ❌         | ⚠️      | ❌    | ✅           |

---

## Contributing

To be defined. Expected sections:
- Local dev setup
- Adding a new provider
- Adding a vector store adapter
- Testing guidelines
- PR checklist

---

*LLMesh — built to close the AI tooling gap in the PHP ecosystem.*
