<?php

declare(strict_types=1);

namespace LLMesh\Core\Observability;

use LLMesh\Core\Contracts\UsageInterface;
use LLMesh\Core\Exceptions\RateLimitException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * PSR-3 structured request logger for LLMesh API calls.
 *
 * Log levels:
 *  - **DEBUG** — successful requests (provider, model, tokens, cost, duration)
 *  - **WARNING** — rate-limit errors with `retry_after` if available
 *  - **ERROR** — all other exceptions
 *
 * All log entries use a JSON-serializable context array so they are
 * compatible with structured logging backends (Monolog, Datadog, etc.).
 *
 * The logger **never throws** — all internal logger exceptions are silently
 * swallowed so that observability failures never break the happy path.
 *
 * @example
 * ```php
 * $logger = new RequestLogger($monolog);
 *
 * $logger->logSuccess('openai', 'gpt-4o', $response->getUsage(), 342);
 * $logger->logRateLimit('openai', $rateLimitException);
 * $logger->logError('openai', $exception);
 * ```
 */
final class RequestLogger implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Create a RequestLogger that discards all output.
     */
    public static function null(): self
    {
        return new self(new NullLogger());
    }

    // =========================================================================
    // Logging methods
    // =========================================================================

    /**
     * Log a successful API call at DEBUG level.
     *
     * @param string          $provider   Provider identifier (e.g. 'openai')
     * @param string          $model      Model name (e.g. 'gpt-4o')
     * @param UsageInterface  $usage      Token usage and optional cost
     * @param int             $durationMs Wall-clock time in milliseconds
     */
    public function logSuccess(
        string $provider,
        string $model,
        UsageInterface $usage,
        int $durationMs,
    ): void {
        $this->safeLog('debug', 'LLMesh request completed', [
            'provider'    => $provider,
            'model'       => $model,
            'tokens_in'   => $usage->getInputTokens(),
            'tokens_out'  => $usage->getOutputTokens(),
            'cost_usd'    => $usage->getEstimatedCost(),
            'duration_ms' => $durationMs,
            'status'      => 'success',
        ]);
    }

    /**
     * Log a rate-limit exception at WARNING level.
     *
     * @param string             $provider   Provider identifier
     * @param RateLimitException $exception  The rate-limit exception
     * @param string             $model      Model name (optional)
     */
    public function logRateLimit(
        string $provider,
        RateLimitException $exception,
        string $model = '',
    ): void {
        $this->safeLog('warning', 'LLMesh request rate-limited', [
            'provider'    => $provider,
            'model'       => $model,
            'retry_after' => $exception->retryAfter(),
            'message'     => $exception->getMessage(),
            'status'      => 'rate_limited',
        ]);
    }

    /**
     * Log any non-rate-limit exception at ERROR level.
     *
     * @param string     $provider  Provider identifier
     * @param \Throwable $exception The exception
     * @param string     $model     Model name (optional)
     */
    public function logError(
        string $provider,
        \Throwable $exception,
        string $model = '',
    ): void {
        $this->safeLog('error', 'LLMesh request failed', [
            'provider'       => $provider,
            'model'          => $model,
            'exception_type' => get_class($exception),
            'message'        => $exception->getMessage(),
            'status'         => 'error',
        ]);
    }

    /**
     * Build a structured context array suitable for a log entry.
     *
     * Useful when callers want to compose context manually before logging.
     *
     * @param string          $provider
     * @param string          $model
     * @param UsageInterface  $usage
     * @param int             $durationMs
     * @param string          $status
     *
     * @return array{provider: string, model: string, tokens_in: int, tokens_out: int, cost_usd: float|null, duration_ms: int, status: string}
     */
    public function buildContext(
        string $provider,
        string $model,
        UsageInterface $usage,
        int $durationMs,
        string $status = 'success',
    ): array {
        return [
            'provider'    => $provider,
            'model'       => $model,
            'tokens_in'   => $usage->getInputTokens(),
            'tokens_out'  => $usage->getOutputTokens(),
            'cost_usd'    => $usage->getEstimatedCost(),
            'duration_ms' => $durationMs,
            'status'      => $status,
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Delegate to the PSR-3 logger, swallowing any logger exceptions.
     *
     * Observability must never crash the main execution path.
     */
    private function safeLog(string $level, string $message, array $context): void
    {
        try {
            $this->logger->{$level}($message, $context);
        } catch (\Throwable) {
            // Intentionally swallowed — logger failures must never propagate
        }
    }
}
