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
use LLMesh\Core\Generators\GenerateObjectOptions;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Core\Generators\ObjectGenerator;
use LLMesh\Core\Generators\ObjectResponse;
use LLMesh\Core\Generators\StreamGenerator;
use LLMesh\Core\Generators\StreamResponse;
use LLMesh\Core\Generators\TextGenerator;
use LLMesh\Core\Generators\TextResponse;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Main entry point for LLMesh operations.
 *
 * This is a static facade providing a fluent API for AI operations.
 */
final class LLMesh
{
    /**
     * @var EventDispatcherInterface|null
     */
    private static EventDispatcherInterface|null $eventDispatcher = null;

    /**
     * Set the event dispatcher for all operations.
     *
     * @param EventDispatcherInterface|null $dispatcher
     */
    public static function withEventDispatcher(EventDispatcherInterface|null $dispatcher): void
    {
        self::$eventDispatcher = $dispatcher;
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
    public static function generateText(
        ProviderInterface $provider,
        GenerateTextOptions $options,
    ): TextResponse {
        $startTime = microtime(true);
        $providerName = self::getProviderName($provider);

        try {
            // Dispatch GenerationStarted event
            self::dispatch(new GenerationStarted($providerName, $options));

            // Generate text
            $generator = new TextGenerator($provider);
            $response = $generator->generate($options);

            // Calculate duration
            $durationMs = (int)((microtime(true) - $startTime) * 1000);

            // Dispatch GenerationCompleted event
            self::dispatch(new GenerationCompleted($providerName, $response, $durationMs));

            return $response;
        } catch (\Throwable $exception) {
            // Dispatch GenerationFailed event
            self::dispatch(new GenerationFailed($providerName, $exception));

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
    public static function generateObject(
        ProviderInterface $provider,
        GenerateObjectOptions $options,
    ): ObjectResponse {
        $startTime    = microtime(true);
        $providerName = self::getProviderName($provider);

        try {
            self::dispatch(new ObjectGenerationStarted($providerName, $options));

            $generator = new ObjectGenerator($provider);
            $response  = $generator->generate($options);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            self::dispatch(new ObjectGenerationCompleted($providerName, $response, $durationMs));

            return $response;
        } catch (\Throwable $exception) {
            self::dispatch(new GenerationFailed($providerName, $exception));
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
    public static function streamText(
        ProviderInterface $provider,
        GenerateTextOptions $options,
    ): StreamResponse {
        $startTime    = microtime(true);
        $providerName = self::getProviderName($provider);

        // Dispatch StreamStarted before the provider is called
        self::dispatch(new StreamStarted($providerName, $options));

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
                    self::dispatch(new StreamChunkReceived($chunk, $chunkIndex));
                    $chunkIndex++;
                    yield $chunk;
                }

                $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                self::dispatch(new StreamCompleted($providerName, $chunkIndex, $durationMs));
            } catch (\Throwable $exception) {
                self::dispatch(new StreamFailed($providerName, $exception));
                throw $exception;
            }
        })();

        return new StreamResponse($instrumentedGenerator);
    }

    /**
     * Get a human-readable provider name from a provider instance.
     *
     * @param ProviderInterface $provider
     * @return string
     */
    private static function getProviderName(ProviderInterface $provider): string
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
    private static function dispatch(object $event): void
    {
        if (self::$eventDispatcher !== null) {
            self::$eventDispatcher->dispatch($event);
        }
    }
}
