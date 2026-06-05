<?php

declare(strict_types=1);

namespace LLMesh\Core\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Factory for creating HttpClient instances with auto-discovery of PSR-18 clients.
 */
class HttpClientFactory
{
    /**
     * Create and return a configured HttpClient.
     *
     * Auto-discovers Guzzle if available, otherwise uses any installed PSR-18 client.
     *
     * @return HttpClient
     *
     * @throws \RuntimeException If no PSR-18 client can be discovered
     */
    public static function make(): HttpClient
    {
        $client = self::discoverClient();
        $requestFactory = self::discoverRequestFactory();
        $streamFactory = self::discoverStreamFactory();

        return new HttpClient($client, $requestFactory, $streamFactory);
    }

    /**
     * Discover a PSR-18 HTTP client.
     *
     * @return ClientInterface
     *
     * @throws \RuntimeException If no client can be discovered
     */
    private static function discoverClient(): ClientInterface
    {
        // Try Guzzle first
        if (class_exists(\GuzzleHttp\Client::class)) {
            return new \GuzzleHttp\Client();
        }

        // Try any other installed PSR-18 client
        $possibleClients = [
            \Http\Client\Socket\Client::class,
            \Http\Client\Curl\Client::class,
        ];

        foreach ($possibleClients as $clientClass) {
            if (class_exists($clientClass)) {
                return new $clientClass();
            }
        }

        throw new \RuntimeException(
            'No PSR-18 HTTP client discovered. Please install guzzlehttp/guzzle or another PSR-18 client.'
        );
    }

    /**
     * Discover a PSR-17 request factory.
     *
     * @return RequestFactoryInterface
     *
     * @throws \RuntimeException If no factory can be discovered
     */
    private static function discoverRequestFactory(): RequestFactoryInterface
    {
        if (class_exists(\GuzzleHttp\Psr7\HttpFactory::class)) {
            return new \GuzzleHttp\Psr7\HttpFactory();
        }

        if (class_exists(\Http\Message\MessageFactory::class)) {
            return new \Http\Message\MessageFactory();
        }

        throw new \RuntimeException(
            'No PSR-17 request factory discovered. Please install guzzlehttp/psr7 or http-interop/http-factory.'
        );
    }

    /**
     * Discover a PSR-17 stream factory.
     *
     * @return StreamFactoryInterface
     *
     * @throws \RuntimeException If no factory can be discovered
     */
    private static function discoverStreamFactory(): StreamFactoryInterface
    {
        if (class_exists(\GuzzleHttp\Psr7\HttpFactory::class)) {
            return new \GuzzleHttp\Psr7\HttpFactory();
        }

        if (class_exists(\Http\Message\StreamFactory::class)) {
            return new \Http\Message\StreamFactory();
        }

        throw new \RuntimeException(
            'No PSR-17 stream factory discovered. Please install guzzlehttp/psr7 or http-interop/http-factory.'
        );
    }
}
