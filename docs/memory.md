# Conversation Memory

LLMesh includes pluggable conversation memory stores to retain dialog history across chat sessions. This enables building multi-turn chatbots and agents that remember previous interactions.

---

## 1. Supported Memory Stores

All memory stores implement the `MemoryStoreInterface` and can be swapped transparently.

### `InMemoryStore`
Stores conversation messages in a PHP array during the current request lifecycle. Suitable for tests and short scripts.
```php
use LLMesh\Core\Memory\InMemoryStore;

$store = new InMemoryStore();
```

### `RedisStore`
Backed by Redis, supporting both `ext-redis` (`\Redis`) and `predis/predis` clients. Refreshes a session TTL on every turn.
```php
use LLMesh\Core\Memory\RedisStore;

// Works with standard \Redis or \Predis\Client instances
$store = new RedisStore($redisClient, prefix: 'chat:session:', ttl: 3600);
```

### `DatabaseStore`
Backed by any relational database connection via PDO (SQLite, MySQL, PostgreSQL, SQL Server).
```php
use LLMesh\Core\Memory\DatabaseStore;

$pdo = new PDO('mysql:host=localhost;dbname=llmesh', 'username', 'password');
$store = new DatabaseStore($pdo, table: 'llmesh_memory');

// Run once to create the table structure
$store->createTable();
```

---

## 2. Using Memory in Text Generation

When memory is attached to `GenerateTextOptions`, LLMesh automatically loads the conversation history before calling the model and saves the new user message and assistant reply upon a successful response.

```php
use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\OpenAI\OpenAIProvider;

$options = GenerateTextOptions::make()
    ->withPrompt('What did I say my favorite color was?')
    ->withMemory($store, sessionId: 'user-session-123');

$response = LLMesh::generateText(new OpenAIProvider($apiKey), $options);
```

---

## 3. Custom Memory Stores

You can implement custom memory backends (e.g. MongoDB, file-system, DynamoDB) by implementing the `MemoryStoreInterface` contract:

```php
namespace App\Memory;

use LLMesh\Core\Contracts\MemoryStoreInterface;

class FileMemoryStore implements MemoryStoreInterface
{
    public function append(string $sessionId, array $message): void
    {
        // Save message array (role, content, etc.) to file
    }

    public function get(string $sessionId): array
    {
        // Retrieve and return all messages for session
    }

    public function clear(string $sessionId): void
    {
        // Delete session file
    }

    public function exists(string $sessionId): bool
    {
        // Check if session file exists
    }
}
```
