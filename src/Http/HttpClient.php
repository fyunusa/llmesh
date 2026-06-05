<?php

declare(strict_types=1);

namespace LLMesh\Core\Http;

use LLMesh\Core\Exceptions\ConnectionException;
use LLMesh\Core\Exceptions\HttpException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * PSR-18 compliant HTTP client wrapper for LLM API calls.
 */
class HttpClient
{
    /**
     * Base URL for requests.
     *
     * @var string
     */
    private string $baseUrl = '';

    /**
     * Request timeout in seconds.
     *
     * @var int
     */
    private int $timeout = 30;

    /**
     * Additional headers to include.
     *
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * Constructor.
     *
     * @param ClientInterface $client PSR-18 HTTP client
     * @param RequestFactoryInterface $requestFactory PSR-17 request factory
     * @param StreamFactoryInterface $streamFactory PSR-17 stream factory
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * Set the base URL for requests.
     *
     * @param string $url Base URL
     *
     * @return self
     */
    public function setBaseUrl(string $url): self
    {
        $this->baseUrl = rtrim($url, '/');

        return $this;
    }

    /**
     * Set the request timeout.
     *
     * @param int $seconds Timeout in seconds
     *
     * @return self
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Add a header to all requests.
     *
     * @param string $key Header name
     * @param string $value Header value
     *
     * @return self
     */
    public function withHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * Send a JSON POST request and return decoded response.
     *
     * @param string $url Request URL
     * @param array $payload Request payload
     * @param array $headers Additional headers
     *
     * @return array Decoded response
     *
     * @throws HttpException On non-2xx response
     * @throws ConnectionException On network failure
     */
    public function post(string $url, array $payload, array $headers = []): array
    {
        $url = $this->buildUrl($url);
        $headers = array_merge($this->headers, $headers);
        $headers['Content-Type'] = 'application/json';

        $body = $this->streamFactory->createStream(json_encode($payload, JSON_THROW_ON_ERROR));

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withBody($body);

        foreach ($headers as $key => $value) {
            $request = $request->withHeader($key, $value);
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (\Throwable $e) {
            throw new ConnectionException(
                "Network error: {$e->getMessage()}",
                0,
                $e,
            );
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $body = (string) $response->getBody();

            throw new HttpException(
                "HTTP {$statusCode}: {$body}",
                $statusCode,
                $body,
            );
        }

        $body = (string) $response->getBody();

        return json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Send a streaming POST request and yield SSE lines.
     *
     * @param string $url Request URL
     * @param array $payload Request payload
     * @param array $headers Additional headers
     *
     * @return \Generator Yields raw SSE lines as strings
     *
     * @throws HttpException On non-2xx response
     * @throws ConnectionException On network failure
     */
    public function stream(string $url, array $payload, array $headers = []): \Generator
    {
        $url = $this->buildUrl($url);
        $headers = array_merge($this->headers, $headers);
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'text/event-stream';

        $body = $this->streamFactory->createStream(json_encode($payload, JSON_THROW_ON_ERROR));

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withBody($body);

        foreach ($headers as $key => $value) {
            $request = $request->withHeader($key, $value);
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (\Throwable $e) {
            throw new ConnectionException(
                "Network error: {$e->getMessage()}",
                0,
                $e,
            );
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $body = (string) $response->getBody();

            throw new HttpException(
                "HTTP {$statusCode}: {$body}",
                $statusCode,
                $body,
            );
        }

        // Stream the response body line by line
        $stream = $response->getBody();
        $buffer = '';

        while (!$stream->eof()) {
            $chunk = $stream->read(8192);
            $buffer .= $chunk;

            // Split by newlines and yield complete lines
            $lines = explode("\n", $buffer);
            // Keep the last potentially incomplete line in the buffer
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    yield $line;
                }
            }
        }

        // Yield any remaining buffered line
        if ($buffer !== '') {
            $line = trim($buffer);
            if ($line !== '') {
                yield $line;
            }
        }
    }

    /**
     * Build full URL from relative path.
     *
     * @param string $url Relative or absolute URL
     *
     * @return string
     */
    private function buildUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if ($this->baseUrl === '') {
            return $url;
        }

        return $this->baseUrl . '/' . ltrim($url, '/');
    }
}
