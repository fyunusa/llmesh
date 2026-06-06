# Text Generation API

The text generation API is the core entry point for prompting LLM models in a single-turn or multi-turn fashion.

---

## Basic Text Generation

You call `LLMesh::generateText()` to prompt an LLM provider and receive a completed `TextResponse`.

```php
use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\OpenAI\OpenAIProvider;

$provider = new OpenAIProvider($apiKey);

$response = LLMesh::generateText(
    $provider,
    GenerateTextOptions::make()
        ->withPrompt('Explain quantum computing in one sentence.')
);

echo $response->getText();
```

---

## Configuration Options

`GenerateTextOptions` provides a fluent, immutable builder interface to customize parameter configurations:

| Method | Description |
|---|---|
| `withPrompt(string $prompt)` | Set a single user prompt. |
| `withSystem(string $prompt)` | Set the system prompt context. |
| `withMessages(array $messages)` | Pass an array of `Message` DTOs (for multi-turn chat). |
| `withTemperature(float $temp)` | Adjust temperature (randomness level between `0.0` and `2.0`). |
| `withMaxTokens(int $max)` | Limit the maximum number of output completion tokens. |
| `withStopSequences(array $seqs)` | List of stop sequences to halt model output. |

### Advanced Example

```php
$options = GenerateTextOptions::make()
    ->withPrompt('Generate a summary of our database architecture.')
    ->withSystem('You are a senior database administrator. Be brief.')
    ->withTemperature(0.3)
    ->withMaxTokens(250);

$response = LLMesh::generateText($provider, $options);
```

---

## Response DTO (`TextResponse`)

The result returned from `generateText()` is a standard `TextResponse` DTO that abstracts provider-specific payloads:

```php
// Get the final text string
$text = $response->getText();

// Get the token usage details
$usage = $response->getUsage();
echo "Input tokens: " . $usage->getInputTokens() . "\n";
echo "Output tokens: " . $usage->getOutputTokens() . "\n";
echo "Estimated Cost: $" . $usage->getEstimatedCost() . "\n";

// Get raw provider payload (useful for custom provider debugging)
$raw = $response->getRaw();
```
