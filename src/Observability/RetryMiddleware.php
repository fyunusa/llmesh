<?php

declare(strict_types=1);

namespace LLMesh\Core\Observability;

use LLMesh\Core\Contracts\EmbeddingResponseInterface;
use LLMesh\Core\Contracts\ResponseInterface;
use LLMesh\Core\Contracts\StreamInterface;
use LLMesh\Core\Exceptions\ConnectionException;
use LLMesh\Core\Exceptions\RateLimitException;
use LLMesh\Core\Exceptions\TokenLimitException;
use LLMesh\Core\Exceptions\ValidationException;

/**
 * Provider middleware that automatically retries failed requests.
 *
 * Retries on RateLimitException and ConnectionException with exponential backoff and jitter.
 * Does NOT retry on TokenLimitException or ValidationException.
 */
class RetryMiddleware extends AbstractMiddleware
{
    /**
     * Constructor.
     *
     * @param int $maxAttempts  Maximum number of retry attempts
     * @param int $baseDelayMs  Base delay in milliseconds for the first retry
     */
    public function __construct(
        private readonly int $maxAttempts = 3,
        private readonly int $baseDelayMs = 100,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function chat(array $messages, array $options = []): ResponseInterface
    {
        return $this->execute(fn () => $this->next->chat($messages, $options));
    }

    /**
     * {@inheritDoc}
     */
    public function stream(array $messages, array $options = []): StreamInterface
    {
        return $this->execute(fn () => $this->next->stream($messages, $options));
    }

    /**
     * {@inheritDoc}
     */
    public function embed(string|array $input, array $options = []): EmbeddingResponseInterface
    {
        return $this->execute(fn () => $this->next->embed($input, $options));
    }

    /**
     * Execute a callback with retry logic.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function execute(callable $callback): mixed
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

                if ($attempt === $this->maxAttempts - 1) {
                    throw $e;
                }

                $delayMs = $this->calculateRetryAfter($e, $attempt);
                $this->sleep($delayMs);
                $attempt++;
            } catch (ConnectionException $e) {
                $lastException = $e;

                if ($attempt === $this->maxAttempts - 1) {
                    throw $e;
                }

                $delayMs = $this->calculateBackoffDelay($attempt);
                $this->sleep($delayMs);
                $attempt++;
            }
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        throw new \RuntimeException('Retry middleware reached maximum attempts without result');
    }

    /**
     * Calculate delay in milliseconds, respecting Retry-After header if present.
     */
    private function calculateRetryAfter(RateLimitException $e, int $attempt): int
    {
        $retryAfter = $e->retryAfter();

        if ($retryAfter !== null) {
            return $retryAfter * 1000;
        }

        return $this->calculateBackoffDelay($attempt);
    }

    /**
     * Calculate exponential backoff delay with jitter.
     */
    private function calculateBackoffDelay(int $attempt): int
    {
        $exponentialDelay = $this->baseDelayMs * (2 ** $attempt);
        $jitter = random_int(0, (int) $exponentialDelay);
        $maxDelay = 5 * 60 * 1000; // 5 minutes cap

        return (int) min($exponentialDelay + $jitter, $maxDelay);
    }

    /**
     * Sleep for the specified number of milliseconds.
     *
     * Protected to allow mocking/overriding in unit tests.
     */
    protected function sleep(int $delayMs): void
    {
        usleep($delayMs * 1000);
    }
}
