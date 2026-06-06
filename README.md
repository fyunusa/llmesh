# LLMesh Core

[![Latest Stable Version](https://poser.pugx.org/llmesh/core/v)](https://packagist.org/packages/llmesh/core)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://packagist.org/packages/llmesh/core)
[![Tests](https://github.com/fyunusa/llmesh/actions/workflows/tests.yml/badge.svg)](https://github.com/fyunusa/llmesh/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

LLMesh is a lightweight, framework-agnostic PHP SDK designed to provide a unified, composable interface for working with different AI models and providers, standardizing how PHP developers build AI-powered applications, tools, and agents.

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

$response = LLMesh::generateText(new OpenAIProvider($apiKey), GenerateTextOptions::make()->withPrompt('Hello!'));
echo $response->getText();
```

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
