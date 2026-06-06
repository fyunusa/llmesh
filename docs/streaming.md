# Streaming Generation API

LLMesh supports real-time text streaming using Server-Sent Events (SSE) or simple iterator-based consumption.

---

## Basic Streaming

You call `LLMesh::streamText()` to begin chunked text generation. The returned `StreamResponse` is **lazy**—the provider API call is deferred until you start iterating over it.

```php
use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\OpenAI\OpenAIProvider;

$provider = new OpenAIProvider($apiKey);

$stream = LLMesh::streamText(
    $provider,
    GenerateTextOptions::make()
        ->withPrompt('Write an essay about renewable energy.')
);

// Consume chunks one by one
foreach ($stream as $chunk) {
    echo $chunk->text; // Yields text increments
}
```

---

## Server-Sent Events (SSE)

For web application endpoints (such as a controller in Laravel or a fast CGI script), you can stream the response directly to the browser with standard SSE headers. LLMesh handles header setting and output buffering.

```php
// Sets text/event-stream headers and flushes chunks immediately as they arrive
$stream->toSSE();
```

---

## Utility Methods

The `StreamResponse` class provides several helper methods to process the stream:

### `toText()`
Exhausts the stream and returns the completely concatenated text string.
```php
$fullText = $stream->toText();
```

### `pipe(Closure $callback)`
Feeds each `ChunkDelta` to a custom callback logic as it is generated.
```php
$stream->pipe(function (ChunkDelta $chunk) {
    Log::debug("Chunk arrived: " . $chunk->text);
});
```

### `getUsage()`
Obtain token usage statistics after the stream has been fully consumed.
> [!NOTE]
> `getUsage()` throws a `StreamNotExhaustedException` if called before the stream is fully consumed.
```php
$usage = $stream->getUsage();
echo "Cost: $" . $usage->getEstimatedCost();
```
