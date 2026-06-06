# Contributing to LLMesh

Thank you for your interest in contributing to LLMesh! This document provides guidelines and instructions for contributing to the project.

## Local Setup

### Prerequisites
- PHP 8.1 or higher
- Composer

### Getting Started

1. Clone the repository:
   ```bash
   git clone https://github.com/llmesh/core.git
   cd core
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Run the test suite:
   ```bash
   composer test
   ```

## Coding Standards

This project follows [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards.

### Before Submitting

- Run the linter:
  ```bash
  composer lint
  ```

- Ensure all tests pass:
  ```bash
  composer test
  ```

- Add tests for any new functionality

## Pull Request Checklist

- [ ] Tests pass (`composer test`)
- [ ] Linter passes (`composer lint`)
- [ ] Full PHPDoc added to public methods
- [ ] CHANGELOG.md updated if adding features or fixes
- [ ] No debug code left behind
- [ ] Branch is up-to-date with `main`

## Development Workflow

1. Create a feature branch from `main`
2. Make your changes
3. Write or update tests
4. Ensure all checks pass
5. Submit a pull request with a clear description

## Code Style

- Use type hints for all parameters and return types
- Add PHPDoc blocks for all public methods
- Use immutable objects where possible
- Prefer composition over inheritance
- Follow PSR-4 autoloading conventions

## Extending LLMesh

### How to Add a Provider

To add a new LLM provider integration, follow these steps:

1. **Implement `ProviderInterface`**:
   Create a new class implementing `LLMesh\Core\Contracts\ProviderInterface`.
   ```php
   use LLMesh\Core\Contracts\ProviderInterface;
   use LLMesh\Core\Contracts\ResponseInterface;
   use LLMesh\Core\Contracts\StreamInterface;
   use LLMesh\Core\Contracts\EmbeddingResponseInterface;

   class CustomProvider implements ProviderInterface {
       public function chat(array $messages, array $options = []): ResponseInterface { ... }
       public function stream(array $messages, array $options = []): StreamInterface { ... }
       public function embed(string|array $input, array $options = []): EmbeddingResponseInterface { ... }
       public function supports(string $capability): bool { ... }
   }
   ```
2. **Implement DTOs**:
   - Return an implementation of `ResponseInterface` (typically `TextResponse` or `ObjectResponse`) from `chat()`.
   - Return a `StreamResponse` wrapping your chunk generator from `stream()`.
   - Return an implementation of `EmbeddingResponseInterface` (typically `EmbeddingResponse`) from `embed()`.
3. **Handle Errors**:
   - Throw `LLMesh\Core\Exceptions\ConnectionException` for connection/network failures.
   - Throw `LLMesh\Core\Exceptions\RateLimitException` for 429 rate limit errors (including `retryAfter` if known).
   - Throw `LLMesh\Core\Exceptions\ValidationException` for invalid request payloads.
4. **Register / Ship as Package**:
   Package the provider (e.g. `llmesh/custom`) or use it directly in your application.

### How to Add a Vector Store

To integrate a new vector database store, follow these steps:

1. **Implement `VectorStoreInterface`**:
   Create a class implementing `LLMesh\Core\Contracts\VectorStoreInterface`.
   ```php
   use LLMesh\Core\Contracts\VectorStoreInterface;
   use LLMesh\Core\RAG\Document;

   class CustomVectorStore implements VectorStoreInterface {
       public function upsert(array $documents): void { ... }
       public function query(array $embedding, int $topK = 5, array $filter = []): array { ... }
       public function delete(array $ids): void { ... }
   }
   ```
2. **Handle Similarity Metric**:
   - Inside `query()`, ensure distance/similarity calculation (e.g. Cosine Similarity) matches your backend capability.
   - Return matching documents sorted by similarity (highest score first).
3. **Write Tests**:
   Create integration tests using SQLite in-memory or mock queries to ensure document structures are preserved.

## Issues

When reporting issues, please include:
- A clear description of the problem
- Steps to reproduce
- Expected vs actual behavior
- PHP version and relevant dependency versions

Thank you for contributing to LLMesh!

