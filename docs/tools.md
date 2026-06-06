# Function & Tool Calling

LLMesh supports native model tool/function calling. You can define callable tools with parameter schemas, supply them to the model, and execute them locally.

---

## 1. Defining Tools

Use the fluent `Tool` builder to declare a tool, specify its input parameter types and requirements, and register a handler execution closure.

```php
use LLMesh\Core\Tools\Tool;

$weatherTool = Tool::make('get_weather')
    ->description('Get current weather for a city')
    ->parameters([
        'city' => Tool::string('The name of the city')->required(),
        'unit' => Tool::enum(['celsius', 'fahrenheit'])->default('celsius'),
    ])
    ->handler(function (array $params): array {
        // Run local business logic
        return [
            'temperature' => 28,
            'condition'   => 'sunny',
            'city'        => $params['city'],
        ];
    });
```

### Parameter Types
- `Tool::string(string $description)`
- `Tool::integer(string $description)`
- `Tool::number(string $description)` (float)
- `Tool::boolean(string $description)`
- `Tool::enum(array $values, string $description)`

---

## 2. Using Tools in Text Generation

When you provide tools to a generation request, the LLM will decide whether it needs to invoke a tool to answer the prompt.

```php
use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\OpenAI\OpenAIProvider;

$options = GenerateTextOptions::make()
    ->withPrompt('What is the weather like in London right now?')
    ->withTools([$weatherTool]);

$response = LLMesh::generateText(new OpenAIProvider($apiKey), $options);

// If the model decides to call the tool, it returns a tool call request.
// LLMesh handles tool parsing internally.
```

---

## 3. Automatic Execution (Tool Loop)

By default, calling `generateText()` with tools executes a single generation turn. If you want LLMesh to automatically execute the requested tools, feed the results back to the model, and loop until a final answer is returned, you should use **Agents** (see [Agents Documentation](agents.md)) which handles this orchestrating cycle automatically.
