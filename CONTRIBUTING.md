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

## Issues

When reporting issues, please include:
- A clear description of the problem
- Steps to reproduce
- Expected vs actual behavior
- PHP version and relevant dependency versions

Thank you for contributing to LLMesh!
