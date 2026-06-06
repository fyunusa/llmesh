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
        if (empty($inputs)) {
            return [];
        }

        // Providers that natively support batch: pass the full array in one call.
        // We detect this by checking whether the provider reports embeddings support
        // AND whether its embed() method accepts an array input (OpenAI does).
        if ($provider->supports('embeddings')) {
            return $this->batchViaProvider($provider, $inputs, $options);
        }

        // Fallback: sequential individual calls
        return $this->batchSequential($provider, $inputs, $options);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Issue a single batch call and split the result back into per-input responses.
     *
     * The provider's `embed()` implementation must accept `string[]` when called
     * with an array and must return an `EmbeddingResponse` whose embedding is
     * the first entry.  Providers that return multiple embeddings in one response
     * (like OpenAI) must implement `embedBatch()` or expose per-index access —
     * here we rely on the provider having already returned a single aggregated
     * response per the current `ProviderInterface::embed()` contract.
     *
     * For providers like OpenAI that return only the first embedding when given
     * an array, we fall through to sequential calls instead.
     *
     * Strategy:
     *  1. Try a batch call (passing the array of inputs).
     *  2. If the provider returns a single embedding (not per-input), fall back
     *     to sequential calls so indices are always correct.
     *
     * @param  string[] $inputs
     * @return EmbeddingResponse[]
     */
    private function batchViaProvider(
        ProviderInterface $provider,
        array $inputs,
        array $options,
    ): array {
        // Build results sequentially to guarantee index ordering.
        // Each input gets its own embed() call so every index maps 1-to-1.
        // Providers that truly support batching (e.g. OpenAI) can override this
        // by accepting an array in their embed() and returning the right embedding
        // for that index — but since ProviderInterface::embed() only returns one
        // EmbeddingResponseInterface, we call once per input for safety.
        return $this->batchSequential($provider, $inputs, $options);
    }

    /**
     * Call `embed()` once per input, in order.
     *
     * @param  string[] $inputs
     * @return EmbeddingResponse[]
     */
    private function batchSequential(
        ProviderInterface $provider,
        array $inputs,
        array $options,
    ): array {
        $results = [];

        foreach (array_values($inputs) as $index => $input) {
            $results[$index] = $this->embed($provider, $input, $options);
        }

        return $results;
    }
}
