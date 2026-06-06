<?php

declare(strict_types=1);

namespace LLMesh\Core\Embeddings;

use LLMesh\Core\Contracts\ProviderInterface;

/**
 * Generates embeddings using a provider.
 *
 * Handles both single and batch inputs, falling back to sequential individual
 * calls when the provider does not support the `'embeddings'` batch capability.
 *
 * @example
 *   $generator = new EmbeddingGenerator();
 *   $response  = $generator->embed($provider, 'Hello world');
 *   $batch     = $generator->embedBatch($provider, ['Hello', 'World']);
 */
final class EmbeddingGenerator
{
    /**
     * Embed a single string input.
     *
     * @param  ProviderInterface $provider LLM provider
     * @param  string            $input    Text to embed
     * @param  array             $options  Provider-specific options (e.g. `model`, `dimensions`)
     * @return EmbeddingResponse
     */
    public function embed(
        ProviderInterface $provider,
        string $input,
        array $options = [],
    ): EmbeddingResponse {
        if (!$provider->supports('embeddings')) {
            throw new \RuntimeException(
                sprintf(
                    'Provider "%s" does not support embeddings. Use a provider that returns true for supports("embeddings").',
                    get_class($provider)
                )
            );
        }

        $raw = $provider->embed($input, $options);

        // Ensure we always return our concrete EmbeddingResponse
        if ($raw instanceof EmbeddingResponse) {
            return $raw;
        }

        // Wrap a foreign EmbeddingResponseInterface implementation
        return new EmbeddingResponse(
            embedding:  $raw->getEmbedding(),
            dimensions: $raw->getDimensions(),
            usage:      $raw->getUsage(),
            model:      '',
        );
    }

    /**
     * Embed multiple inputs.
     *
     * When the provider supports `'embeddings'` (i.e. `supports('embeddings')` returns `true`),
     * a single batch API call is made with all inputs at once.
     *
     * When the provider does NOT support batch embeddings, individual calls are
     * made sequentially and the results are assembled in input order.
     *
     * The returned array is always indexed to match the input array — index 0 of
     * the input corresponds to index 0 of the output, regardless of provider
     * response ordering.
     *
     * @param  ProviderInterface $provider LLM provider
     * @param  string[]          $inputs   Texts to embed
     * @param  array             $options  Provider-specific options
     * @return EmbeddingResponse[]         One response per input, same order as inputs
     */
    public function embedBatch(
        ProviderInterface $provider,
        array $inputs,
        array $options = [],
    ): array {
        if (!$provider->supports('embeddings')) {
            throw new \RuntimeException(
                sprintf(
                    'Provider "%s" does not support embeddings. Use a provider that returns true for supports("embeddings").',
                    get_class($provider)
                )
            );
        }

        if (empty($inputs)) {
            return [];
        }

        $inputValues = array_values($inputs);
        $originalKeys = array_keys($inputs);

        // If provider supports native batch embedding
        if ($provider->supports('batch_embeddings')) {
            $responses = $provider->embedBatch($inputValues, $options);

            $results = [];
            foreach ($originalKeys as $i => $key) {
                $raw = $responses[$i];
                if ($raw instanceof EmbeddingResponse) {
                    $results[$key] = $raw;
                } else {
                    $results[$key] = new EmbeddingResponse(
                        embedding:  $raw->getEmbedding(),
                        dimensions: $raw->getDimensions(),
                        usage:      $raw->getUsage(),
                        model:      '',
                    );
                }
            }
            return $results;
        }

        // Sequential fallback
        $results = [];
        foreach ($originalKeys as $i => $key) {
            $results[$key] = $this->embed($provider, $inputValues[$i], $options);
        }
        return $results;
    }
}
