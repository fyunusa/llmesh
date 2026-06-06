# Structured Output Generation

LLMesh allows you to generate type-safe, validated structured JSON output from LLM models using a schema-first approach.

---

## Output Modes

LLMesh supports two modes for generating structured outputs (configured automatically based on the provider and capability):

1. **`OutputMode::JSON_MODE`**: The JSON Schema is injected into the system prompt, instructing the model to return a structured JSON string, which is then validated.
2. **`OutputMode::TOOL_MODE`**: Leverages the model's native tool/function calling capabilities to yield schema-conforming outputs, ensuring much higher reliability.

---

## 1. Schema Builder

LLMesh contains a fluent, schema-agnostic builder to define the structure of the desired output.

### Supported Types
- `Schema::string()`
- `Schema::integer()`
- `Schema::number()` (float)
- `Schema::boolean()`
- `Schema::array($itemSchema)`
- `Schema::object($properties)`
- `Schema::enum($values)`

### Fluent Modifiers
- `->required()`
- `->description(string $desc)`
- `->nullable()`
- `->minimum(int $min)` / `->maximum(int $max)`
- `->minLength(int $len)` / `->maxLength(int $len)`

---

## 2. Basic Example

```php
use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateObjectOptions;
use LLMesh\Core\Schema\Schema;
use LLMesh\OpenAI\OpenAIProvider;

// 1. Define the schema
$schema = Schema::object([
    'name'      => Schema::string()->required()->description('The person\'s full name'),
    'age'       => Schema::integer()->required()->minimum(0),
    'interests' => Schema::array(Schema::string())->required(),
])->required(['name', 'age', 'interests']);

// 2. Generate the structured object
$response = LLMesh::generateObject(
    new OpenAIProvider($apiKey),
    GenerateObjectOptions::make()
        ->withPrompt('Extract information from: Bob is a 45-year-old engineer who loves coding and gardening.')
        ->withSchema($schema)
);

// 3. Access the parsed array
print_r($response->object);
/*
Array
(
    [name] => Bob
    [age] => 45
    [interests] => Array
        (
            [0] => coding
            [1] => gardening
        )
)
*/
```

---

## 3. Retries on Malformed JSON

Under the hood, if the LLM's response fails validation or is not valid JSON, LLMesh automatically catches the exception and retries the request once (feeding the validation errors back to the model as prompt feedback) to automatically self-correct formatting errors.
