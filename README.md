# LLMesh Core

[![Latest Stable Version](https://poser.pugx.org/llmesh/core/v)](https://packagist.org/packages/llmesh/core)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://packagist.org/packages/llmesh/core)
[![Tests](https://github.com/fyunusa/llmesh/actions/workflows/tests.yml/badge.svg)](https://github.com/fyunusa/llmesh/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

LLMesh is a lightweight, framework-agnostic PHP SDK designed to provide a unified interface for working with different AI providers.

---

## Installation

Install the core library along with the OpenAI provider:

```bash
composer require llmesh/core llmesh/openai
```

---

## Quick Start

```php
use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\OpenAI\OpenAIProvider;

$response = LLMesh::generateText(
    new OpenAIProvider($apiKey),
    GenerateTextOptions::make()->withPrompt('Explain recursion in 1 sentence.')
);

echo $response->getText();
```

---

## Core Features

| Feature | Description | Documentation |
| --- | --- | --- |
| **Text Generation** | Simple unified API for chat completions | [Docs](docs/generators.md) |
| **Streaming** | Stream chunked text responses in real-time | [Docs](docs/streaming.md) |
| **Structured Output** | Generate JSON validated against strict schemas | [Docs](docs/structured-output.md) |
| **Tool Calling** | Native tool & function calling with schema validation | [Docs](docs/tools.md) |
| **Agent Loop** | Multi-step agent reasoning with tool execution | [Docs](docs/agents.md) |
| **Conversation Memory** | Pluggable stores (In-Memory, Redis, Eloquent) | [Docs](docs/memory.md) |
| **RAG Pipeline** | Loaders, Splitters, and Vector Search | [Docs](docs/rag.md) |
| **Embeddings** | High-performance batch embedding generator | [Docs](docs/embeddings.md) |
| **Observability** | Request logging, retries, and cost tracking | [Docs](docs/observability.md) |

---

## Documentation

- [Getting Started](docs/getting-started.md)
- [Architecture Guide](docs/architecture.md)
- [Observability & Middleware](docs/observability.md)
- [Contributing Guide](CONTRIBUTING.md)

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
