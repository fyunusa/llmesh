# LLMesh

[![Latest Stable Version](https://poser.pugx.org/llmesh/core/v)](https://packagist.org/packages/llmesh/core)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://packagist.org/packages/llmesh/core)
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
- **[tools](docs/tools.md)**: Native function/tool calling with parameter mapping and validation.
- **[agents](docs/agents.md)**: Autonomous multi-step orchestration loop with tool execution.
- **[memory](docs/memory.md)**: Pluggable conversation memory stores (In-Memory, Redis, Database).
- **[RAG](docs/rag.md)**: End-to-end ingestion pipeline (load, split, embed, store, retrieve).

---

## Contributing

Please see the [CONTRIBUTING.md](CONTRIBUTING.md) guide for information on local setup, extending LLMesh with custom providers or vector stores, and the pull request checklist.

---

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for details.
