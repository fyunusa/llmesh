# LLMesh

[![Latest Stable Version](https://poser.pugx.org/llmesh/core/v)](https://packagist.org/packages/llmesh/core)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://packagist.org/packages/llmesh/core)
[![Laravel Supported](https://img.shields.io/badge/Laravel-supported-brightgreen.svg)](https://packagist.org/packages/llmesh/laravel)
[![OpenAI Supported](https://img.shields.io/badge/OpenAI-supported-purple.svg)](https://packagist.org/packages/llmesh/openai)
[![Claude Supported](https://img.shields.io/badge/Claude-supported-red.svg)](https://packagist.org/packages/llmesh/anthropic)
[![Structured Extraction](https://img.shields.io/badge/Structured%20Extraction-Pydantic--style-blue.svg)](#structured-extraction)
[![Tests](https://github.com/fyunusa/llmesh/actions/workflows/tests.yml/badge.svg)](https://github.com/fyunusa/llmesh/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

LLMesh is a flexible PHP SDK designed to help developers build AI-powered applications and autonomous agents.

---

## Why use LLMesh?

Integrating large language models (LLMs) into applications is complicated and heavily dependent on the specific model provider you use.

LLMesh standardizes integrating artificial intelligence (AI) models across supported providers. This enables developers to focus on building great AI applications, not waste time on technical details.

For example, here’s how you can generate text with various models using LLMesh:

```php
use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\OpenAI\OpenAIProvider;

$response = LLMesh::generateText(
    new OpenAIProvider($apiKey),
    GenerateTextOptions::make()->withPrompt('What is love?')
);

echo $response->getText();
```

---

## Installation

Install the core library along with a provider (e.g. OpenAI):

```bash
composer require llmesh/core llmesh/openai
```

---

## Decoupled Packages

The LLMesh ecosystem consists of separate, modular packages:

- **[LLMesh Core](https://github.com/fyunusa/llmesh)**: The unified interfaces, memory builder, RAG pipeline, and orchestration loop.
- **[OpenAI Provider](https://github.com/fyunusa/llmesh-openai)**: Provider adapter for OpenAI models.
- **[Anthropic Provider](https://github.com/fyunusa/llmesh-anthropic)**: Provider adapter for Anthropic Claude models.
- **[Laravel Adapter](https://github.com/fyunusa/llmesh-laravel)**: First-class integration for Laravel applications.

---

## Features

- **[generateText](docs/generators.md)**: Unified API for text generation with full parameter customization.
- **[streamText](docs/streaming.md)**: Real-time chunked text generation and Server-Sent Events (SSE) responses.
- **[generateObject](docs/structured-output.md)**: Type-safe JSON output validated against custom schemas.
- **[Structured Extraction](docs/structured-extraction.md)**: Pydantic-style structured data extraction into fully typed PHP model classes.
- **[tools](docs/tools.md)**: Native function/tool calling with parameter mapping and validation.
- **[agents](docs/agents.md)**: Autonomous multi-step orchestration loop with tool execution.
- **[memory](docs/memory.md)**: Pluggable conversation memory stores (In-Memory, Redis, Database).
- **[RAG](docs/rag.md)**: End-to-end ingestion pipeline (load, split, embed, store, retrieve).

---

## Structured Extraction

LLMesh supports Pydantic-style structured extraction, allowing you to define a PHP class to serve as the JSON schema, validation rules, and typed data container all in a single step.

```php
use LLMesh\Core\LLMesh;
use LLMesh\Core\Structured\LLMModel;
use LLMesh\Core\Structured\Attributes\Field;
use LLMesh\Core\Structured\Attributes\Description;
use LLMesh\OpenAI\OpenAIProvider;

#[Description("An invoice extracted from a text document")]
class Invoice extends LLMModel
{
    public function __construct(
        #[Field(description: "The reference invoice number", example: "INV-1001")]
        public readonly string $invoiceNumber,

        #[Field(description: "Total invoice amount in USD", minimum: 0)]
        public readonly float $totalAmount,

        #[Field(description: "Payment due date")]
        public readonly \DateTimeImmutable $dueDate,
    ) {}

    public function validate(): void
    {
        if ($this->totalAmount < 0) {
            throw new \InvalidArgumentException("Invoice amount cannot be negative.");
        }
    }
}

// 1-line extraction directly into typed PHP object
$invoice = LLMesh::make()->extractFrom(
    Invoice::class,
    "Invoice INV-1001 details: Total $150.00, due on 2026-06-30.",
    new OpenAIProvider($apiKey)
);

echo $invoice->invoiceNumber;             // string: "INV-1001"
echo $invoice->totalAmount;               // float: 150.0
echo $invoice->dueDate->format('Y-m-d');  // DateTimeImmutable: "2026-06-30"
```

The system automatically handles JSON Schema generation, LLM invocation, self-correction/retries, post-deserialization validation, and type-coercion (converting string inputs to `DateTimeImmutable` instances, `BackedEnum` cases, floats, booleans, and nested model hierarchies).

---

## Contributing

Please see the [CONTRIBUTING.md](CONTRIBUTING.md) guide for information on local setup, extending LLMesh with custom providers or vector stores, and the pull request checklist.

---

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for details.
