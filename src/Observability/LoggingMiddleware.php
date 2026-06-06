<?php

declare(strict_types=1);

namespace LLMesh\Core\Observability;

use LLMesh\Core\Contracts\EmbeddingResponseInterface;
use LLMesh\Core\Contracts\ResponseInterface;
use LLMesh\Core\Contracts\StreamInterface;
use LLMesh\Core\Exceptions\RateLimitException;
use Psr\Log\LoggerInterface;

/**
 * Provider middleware that logs every API call via a PSR-3 logger.
 *
 * Log levels:
 *  - **DEBUG** — successful requests (provider, model, tokens, cost, duration_ms)
 *  - **WARNING** — `RateLimitException` (includes `retry_after`)
 *  - **ERROR** — all other exceptions
 *
 * The middleware **never swallows business exceptions** — it re-throws them
 * after logging. Logger exceptions are silently suppressed.
 *
 * @example
 * ```php
 * $provider = MiddlewareStack::wrap($rawProvider)
 *     ->with(new LoggingMiddleware($monolog))
 *     ->build();
 * ```
 */
final class LoggingMiddleware extends AbstractMiddleware
{
    private readonly RequestLogger $requestLogger;

    /**
     * @param LoggerInterface $logger    PSR-3 logger instance
     * @param string          $provider  Human-readable provider label (auto-derived if empty)
     * @param string          $model     Model name to include in log entries (optional)
     */
    public function __construct(
        LoggerInterface $logger,
        private readonly string $provider = '',
        private readonly string $model = '',
    ) {
        $this->requestLogger = new RequestLogger($logger);
    }

    /**
     * {@inheritDoc}
     *
     * Logs successful calls at DEBUG, rate-limit errors at WARNING,
     * and all other errors at ERROR.
     */
    public function chat(array $messages, array $options = []): ResponseInterface
    {
        $startMs = $this->nowMs();

        try {
            $response = $this->next->chat($messages, $options);
            $duration = $this->nowMs() - $startMs;

            $this->requestLogger->logSuccess(
                $this->resolveProvider(),
                $this->resolveModel($options),
                $response->getUsage(),
                $duration,
            );

            return $response;
        } catch (RateLimitException $e) {
            $this->requestLogger->logRateLimit(
                $this->resolveProvider(),
                $e,
                $this->resolveModel($options),
            );
            throw $e;
        } catch (\Throwable $e) {
            $this->requestLogger->logError(
                $this->resolveProvider(),
                $e,
                $this->resolveModel($options),
            );
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     *
     * Stream calls are logged at start (DEBUG) and on errors.
     * Per-chunk logging is intentionally omitted to avoid noise.
     */
    public function stream(array $messages, array $options = []): StreamInterface
    {
        try {
            return $this->next->stream($messages, $options);
        } catch (RateLimitException $e) {
            $this->requestLogger->logRateLimit(
                $this->resolveProvider(),
                $e,
                $this->resolveModel($options),
            );
            throw $e;
        } catch (\Throwable $e) {
            $this->requestLogger->logError(
                $this->resolveProvider(),
                $e,
                $this->resolveModel($options),
            );
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function embed(string|array $input, array $options = []): EmbeddingResponseInterface
    {
        $startMs = $this->nowMs();

        try {
            $response = $this->next->embed($input, $options);
            $duration = $this->nowMs() - $startMs;

            $this->requestLogger->logSuccess(
                $this->resolveProvider(),
                $this->resolveModel($options),
                $response->getUsage(),
                $duration,
            );

            return $response;
        } catch (RateLimitException $e) {
            $this->requestLogger->logRateLimit($this->resolveProvider(), $e);
            throw $e;
        } catch (\Throwable $e) {
            $this->requestLogger->logError($this->resolveProvider(), $e);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function embedBatch(array $inputs, array $options = []): array
    {
        $startMs = $this->nowMs();

        try {
            $responses = $this->next->embedBatch($inputs, $options);
            $duration  = $this->nowMs() - $startMs;

            $inputTokens  = 0;
            $outputTokens = 0;
            $totalCost    = 0.0;
            $anyCostNull  = false;

            foreach ($responses as $res) {
                $u = $res->getUsage();
                $inputTokens  += $u->getInputTokens();
                $outputTokens += $u->getOutputTokens();

                $cost = $u->getEstimatedCost();
                if ($cost === null) {
                    $anyCostNull = true;
                } else {
                    $totalCost += $cost;
                }
            }

            $aggregatedUsage = new \LLMesh\Core\Generators\Usage(
                inputTokens:   $inputTokens,
                outputTokens:  $outputTokens,
                estimatedCost: $anyCostNull ? null : $totalCost,
            );

            $this->requestLogger->logSuccess(
                $this->resolveProvider(),
                $this->resolveModel($options),
                $aggregatedUsage,
                $duration,
            );

            return $responses;
        } catch (RateLimitException $e) {
            $this->requestLogger->logRateLimit($this->resolveProvider(), $e);
            throw $e;
        } catch (\Throwable $e) {
            $this->requestLogger->logError($this->resolveProvider(), $e);
            throw $e;
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function resolveProvider(): string
    {
        if ($this->provider !== '') {
            return $this->provider;
        }

        // Attempt to derive from the inner provider's class name
        $class = get_class($this->next);
        $parts = explode('\\', $class);
        return str_replace('Provider', '', end($parts));
    }

    private function resolveModel(array $options): string
    {
        return $options['model'] ?? $this->model;
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
