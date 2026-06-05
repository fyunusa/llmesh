# LLMesh Core

A flexible PHP SDK for interacting with multiple LLM providers.

LLMesh provides a unified interface for working with different AI providers including OpenAI, Anthropic, and others.

## Installation

```bash
composer require llmesh/core llmesh/openai
```

## Quick Start

```php
use LLMesh\OpenAI\OpenAIProvider;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Core\LLMesh;

$provider = new OpenAIProvider(apiKey: $apiKey);
$response = LLMesh::generateText(
    provider: $provider,
    options: GenerateTextOptions::make()
        ->withPrompt('What is the meaning of life?')
);

echo $response->getText();
```

## Features

- **Text Generation** - Generate text with configurable parameters
- **Streaming** - Stream responses in real-time
- **Structured Output** - Generate validated JSON objects
- **Tool Calling** - Extend models with custom tools
- **Agent Loop** - Build autonomous agents
- **Memory** - Persistent conversation history
- **RAG Pipeline** - Retrieval-augmented generation
- **Embeddings** - Generate embeddings for semantic search

## Documentation

Full documentation coming soon. See the [CONTRIBUTING.md](CONTRIBUTING.md) guide for development instructions.

## License

MIT License - see [LICENSE](LICENSE) for details.
