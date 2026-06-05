<?php

declare(strict_types=1);

namespace LLMesh\Core\Http;

use LLMesh\Core\Exceptions\ConnectionException;
use LLMesh\Core\Exceptions\RateLimitException;
use LLMesh\Core\Exceptions\TokenLimitException;
use LLMesh\Core\Exceptions\ValidationException;

/**
 * Retry handler with exponential backoff and jitter for HTTP requests.
 */
class RetryHandler
{
    /**
     * Constructor.
     *
     * @param int $maxAttempts Maximum number of retry attempts
     */
    public function __construct(private readonly int $maxAttempts = 3)
    {
    }

    /**
     * Execute a callback with retry logic.
     *
     * Retries on RateLimitException and ConnectionException with exponential backoff.
     * Does NOT retry on TokenLimitException, ValidationException, or 4xx errors other than 429.
     *
     * @param callable $callback Callback to execute
     * @param int $baseDelayMs Base delay in milliseconds for first retry
     *
     * @return mixed Result from callback
     *
     * @throws RateLimitException
     * @throws ConnectionException
     * @throws TokenLimitException
     * @throws ValidationException
     * @throws \Exception Other exceptions are rethrown
     */
    public function execute(callable $callback, int $baseDelayMs = 100): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxAttempts) {
            try {
                return $callback();
            } catch (TokenLimitException | ValidationException $e) {
                // Never retry these
                throw $e;
            } catch (RateLimitException $e) {
                $lastException = $e;

                // Check if this is the last attempt
                if ($attempt === $this->maxAttempts - 1) {
                    throw $e;
                }

                // Calculate delay - respect Retry-After header if available
                $delayMs = $this->calculateRetryAfter($e, $attempt, $baseDelayMs);
                $this->sleep($delayMs);
                $attempt++;
            } catch (ConnectionException $e) {
                $lastException = $e;

                // Check if this is the last attempt
                if ($attempt === $this->maxAttempts - 1) {
                    throw $e;
                }

                // Calculate exponential backoff with jitter
                $delayMs = $this->calculateBackoffDelay($attempt, $baseDelayMs);
                $this->sleep($delayMs);
                $attempt++;
            }
        }

        // Should not reach here, but if we do, throw the last exception
        if ($lastException !== null) {
            throw $lastException;
        }

        throw new \RuntimeException('Retry handler reached maximum attempts without result');
    }

    /**
     * Calculate the delay for rate limit exceptions.
     *
     * @param RateLimitException $e The exception
     * @param int $attempt Current attempt number (0-based)
     * @param int $baseDelayMs Base delay in milliseconds
     *
     * @return int Delay in milliseconds
     */
    private function calculateRetryAfter(RateLimitException $e, int $attempt, int $baseDelayMs): int
    {
        $retryAfter = $e->retryAfter();

        if ($retryAfter !== null) {
            // Convert seconds to milliseconds
            return $retryAfter * 1000;
        }

        // Fall back to exponential backoff
        return $this->calculateBackoffDelay($attempt, $baseDelayMs);
    }

    /**
     * Calculate exponential backoff delay with jitter.
     *
     * @param int $attempt Attempt number (0-based)
     * @param int $baseDelayMs Base delay in milliseconds
     *
     * @return int Delay in milliseconds
     */
    private function calculateBackoffDelay(int $attempt, int $baseDelayMs): int
    {
        // Exponential backoff: base * (2 ^ attempt)
        $exponentialDelay = $baseDelayMs * (2 ** $attempt);

        // Add jitter (random value between 0 and delay)
        $jitter = random_int(0, (int) $exponentialDelay);

        // Cap the total delay at a reasonable maximum (5 minutes)
        $maxDelay = 5 * 60 * 1000; // 5 minutes in milliseconds
        $totalDelay = min($exponentialDelay + $jitter, $maxDelay);

        return (int) $totalDelay;
    }

    /**
     * Sleep for the specified number of milliseconds.
     *
     * @param int $delayMs Delay in milliseconds
     *
     * @return void
     */
    private function sleep(int $delayMs): void
    {
        usleep($delayMs * 1000);
    }
}
