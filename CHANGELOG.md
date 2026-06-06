# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-06-06

### Added
- Main entry point facade `LLMesh`
- Core Text Generation (`generateText`) with full parameter customization
- Real-time Streaming (`streamText`) with chunk emission and SSE outputs
- Structured Output (`generateObject`) supporting JSON schema validation
- Native Tool Calling with automatic function execution and schema mappings
- Autonomous Agent Loop with step-by-step reasoning callbacks
- Conversation Memory system supporting pluggable stores (In-Memory, Redis, Eloquent)
- RAG Pipeline with Document Loaders, Text Splitters, and Vector Store search
- High-Performance Embeddings batch generation
- Observability layer: request logging, automatic retries with backoff/jitter, and precise model cost tracking
