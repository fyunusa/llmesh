<?php

declare(strict_types=1);

namespace LLMesh\Core;

use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Events\GenerationCompleted;
use LLMesh\Core\Events\GenerationFailed;
use LLMesh\Core\Events\GenerationStarted;
use LLMesh\Core\Events\ObjectGenerationCompleted;
use LLMesh\Core\Events\ObjectGenerationStarted;
use LLMesh\Core\Events\StreamChunkReceived;
use LLMesh\Core\Events\StreamCompleted;
use LLMesh\Core\Events\StreamFailed;
use LLMesh\Core\Events\StreamStarted;
use LLMesh\Core\Embeddings\EmbeddingGenerator;
use LLMesh\Core\Embeddings\EmbeddingResponse;
use LLMesh\Core\RAG\Pipeline;
use LLMesh\Core\Generators\GenerateObjectOptions;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Core\Generators\ObjectGenerator;
use LLMesh\Core\Generators\ObjectResponse;
use LLMesh\Core\Generators\StreamGenerator;
use LLMesh\Core\Generators\StreamResponse;
use LLMesh\Core\Generators\TextGenerator;
use LLMesh\Core\Generators\TextResponse;
use Psr\EventDispatcher\EventDispatcherInterface;
use LLMesh\Core\Structured\LLMModel;
use LLMesh\Core\Structured\ExtractionOptions;
use LLMesh\Core\Structured\ExtractionGenerator;


/**
 * Main entry point for LLMesh operations.
 *
 * This is an instantiable facade providing a fluent API for AI operations.
 */
final class LLMesh
{
    /**
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * @var EventDispatcherInterface|null
     */
    private ?EventDispatcherInterface $eventDispatcher = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
    }

    /**
     * Get the singleton instance.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create a new instance of LLMesh.
     *
     * @return self
     */
    public static function make(): self
    {
        return new self();
    }


    /**
     * Generate text using the provided provider and options.
     *
     * @param ProviderInterface $provider The LLM provider
     * @param GenerateTextOptions $options Generation options
     * @return TextResponse The generated text response
     *
     * @throws \Throwable
     */
    /**
     * Generate text using the provided provider and options.
     *
     * @param ProviderInterface $provider The LLM provider
     * @param GenerateTextOptions $options Generation options
     * @return TextResponse The generated text response
     *
     * @throws \Throwable
     */
    private function runGenerateText(
        ProviderInterface $provider,
        GenerateTextOptions $options,
    ): TextResponse {
        $startTime = microtime(true);
        $providerName = $this->getProviderName($provider);

        try {
            // Dispatch GenerationStarted event
            $this->dispatch(new GenerationStarted($providerName, $options));

            // Generate text
            $generator = new TextGenerator($provider);
            $response = $generator->generate($options);

            // Calculate duration
            $durationMs = (int)((microtime(true) - $startTime) * 1000);

            // Dispatch GenerationCompleted event
            $this->dispatch(new GenerationCompleted($providerName, $response, $durationMs));

            return $response;
        } catch (\Throwable $exception) {
            // Dispatch GenerationFailed event
            $this->dispatch(new GenerationFailed($providerName, $exception));

            throw $exception;
        }
    }

    /**
     * Generate a structured object using the provided provider and options.
     *
     * Injects the JSON Schema into the system prompt (JSON_MODE) or uses
     * native tool-calling (TOOL_MODE). Retries once on malformed JSON.
     *
     * @param ProviderInterface    $provider The LLM provider
     * @param GenerateObjectOptions $options Generation options (schema required)
     *
     * @return ObjectResponse The validated, parsed response
     *
     * @throws \Throwable
     */
    private function runGenerateObject(
        ProviderInterface $provider,
        GenerateObjectOptions $options,
    ): ObjectResponse {
        $startTime    = microtime(true);
        $providerName = $this->getProviderName($provider);

        try {
            $this->dispatch(new ObjectGenerationStarted($providerName, $options));

            $generator = new ObjectGenerator($provider);
            $response  = $generator->generate($options);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->dispatch(new ObjectGenerationCompleted($providerName, $response, $durationMs));

            return $response;
        } catch (\Throwable $exception) {
            $this->dispatch(new GenerationFailed($providerName, $exception));
            throw $exception;
        }
    }

    /**
     * Stream text generation using the provided provider and options.
     *
     * Events fired:
     *  - {@see StreamStarted}        — immediately, before the first chunk
     *  - {@see StreamChunkReceived}  — once per ChunkDelta as the caller consumes the stream
     *  - {@see StreamCompleted}      — after the last chunk is consumed
     *  - {@see StreamFailed}         — if an exception is thrown during consumption
     *
     * The returned StreamResponse is **lazy** — the provider is not called until
     * the caller begins iterating (foreach, toText(), pipe(), toSSE()).
     *
     * @param ProviderInterface   $provider The LLM provider
     * @param GenerateTextOptions $options  Generation options
     *
     * @return StreamResponse A lazy stream of ChunkDelta objects
     *
     * @throws \RuntimeException If the provider does not support streaming
     * @throws \Throwable        Re-throws any provider exception
     */
    private function runStreamText(
        ProviderInterface $provider,
        GenerateTextOptions $options,
    ): StreamResponse {
        $startTime    = microtime(true);
        $providerName = $this->getProviderName($provider);

        // Dispatch StreamStarted before the provider is called
        $this->dispatch(new StreamStarted($providerName, $options));

        // Obtain the raw StreamResponse from the generator (lazy — no chunks yet)
        $generator = new StreamGenerator($provider);
        $rawStream = $generator->stream($options);

        // Wrap the inner generator in an instrumented generator that fires
        // per-chunk and completion/failure events as the caller consumes it.
        $instrumentedGenerator = (function () use (
            $rawStream,
            $providerName,
            $startTime,
        ): \Generator {
            $chunkIndex = 0;
            try {
                foreach ($rawStream->getChunks() as $chunk) {
                    $this->dispatch(new StreamChunkReceived($chunk, $chunkIndex));
                    $chunkIndex++;
                    yield $chunk;
                }

                $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                $this->dispatch(new StreamCompleted($providerName, $chunkIndex, $durationMs));
            } catch (\Throwable $exception) {
                $this->dispatch(new StreamFailed($providerName, $exception));
                throw $exception;
            }
        })();

        return new StreamResponse($instrumentedGenerator);
    }

    /**
     * Embed a single text input and return the embedding vector.
     *
     * @param ProviderInterface $provider The LLM provider
     * @param string            $input    Text to embed
     * @param array             $options  Provider-specific options
     * @return EmbeddingResponse
     */
    private function runEmbed(
        ProviderInterface $provider,
        string $input,
        array $options = [],
    ): EmbeddingResponse {
        $generator = new EmbeddingGenerator();
        return $generator->embed($provider, $input, $options);
    }

    /**
     * Embed multiple text inputs and return one EmbeddingResponse per input.
     *
     * The returned array is indexed in the same order as `$inputs`.
     *
     * @param ProviderInterface $provider The LLM provider
     * @param string[]          $inputs   Texts to embed
     * @param array             $options  Provider-specific options
     * @return EmbeddingResponse[]
     */
    private function runEmbedBatch(
        ProviderInterface $provider,
        array $inputs,
        array $options = [],
    ): array {
        $generator = new EmbeddingGenerator();
        return $generator->embedBatch($provider, $inputs, $options);
    }

    private function runPipeline(): Pipeline
    {
        return Pipeline::make();
    }

    /**
     * Extract structured data from unstructured text into a typed LLMModel instance.
     *
     * @template T of LLMModel
     * @param ProviderInterface $provider
     * @param ExtractionOptions $options  Must have input and modelClass set
     * @return T
     *
     * @throws \LLMesh\Core\Exceptions\ValidationException on extraction failure
     */
    private function runExtract(
        ProviderInterface $provider,
        ExtractionOptions $options,
    ): LLMModel {
        $generator = new ExtractionGenerator($this->eventDispatcher);
        return $generator->extract($provider, $options);
    }

    /**
     * Shorthand for one-line extraction.
     *
     * @template T of LLMModel
     * @param class-string<T> $modelClass
     * @param string          $input
     * @param ProviderInterface $provider
     * @return T
     */
    private function runExtractFrom(
        string $modelClass,
        string $input,
        ProviderInterface $provider,
    ): LLMModel {
        return $this->runExtract(
            provider: $provider,
            options: ExtractionOptions::make()
                ->withInput($input)
                ->into($modelClass),
        );
    }


    /**
     * Route dynamic instance calls.
     *
     * @param string $name Method name
     * @param array $arguments Method arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        if ($name === 'withEventDispatcher') {
            $this->eventDispatcher = $arguments[0];
            return $this;
        }

        $runMethod = 'run' . ucfirst($name);
        if (method_exists($this, $runMethod)) {
            return $this->$runMethod(...$arguments);
        }

        throw new \BadMethodCallException("Method {$name} does not exist.");
    }

    /**
     * Route static calls to the singleton instance to preserve backward compatibility.
     *
     * @param string $name Method name
     * @param array $arguments Method arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if ($name === 'withEventDispatcher') {
            $instance = self::getInstance();
            $instance->eventDispatcher = $arguments[0];
            return $instance;
        }

        $instance = self::getInstance();
        $runMethod = 'run' . ucfirst($name);
        if (method_exists($instance, $runMethod)) {
            return $instance->$runMethod(...$arguments);
        }

        throw new \BadMethodCallException("Static method {$name} does not exist.");
    }

    /**
     * Get a human-readable provider name from a provider instance.
     *
     * @param ProviderInterface $provider
     * @return string
     */
    private function getProviderName(ProviderInterface $provider): string
    {
        $class = get_class($provider);

        // Handle mock objects from PHPUnit
        if (strpos($class, 'MockObject') !== false || strpos($class, 'Mock_') !== false) {
            return 'Mock';
        }

        $parts = explode('\\', $class);
        $simpleName = end($parts);

        // Remove 'Provider' suffix if present
        return str_replace('Provider', '', $simpleName);
    }

    /**
     * Dispatch an event to the registered event dispatcher.
     *
     * @param object $event
     */
    private function dispatch(object $event): void
    {
        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
