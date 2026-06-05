<?php

declare(strict_types=1);

namespace LLMesh\Core\Generators;

use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Data\Message;
use LLMesh\Core\Exceptions\ValidationException;
use LLMesh\Core\Schema\SchemaInterface;

/**
 * Generates a structured PHP object/array from an LLM response.
 *
 * Two output strategies are supported via `GenerateObjectOptions::$mode`:
 *
 * **JSON_MODE** (default):
 *   Injects the JSON Schema into the system prompt and instructs the model
 *   to respond ONLY with valid JSON matching that schema. The raw text
 *   response is then stripped of any markdown fences and parsed.
 *
 * **TOOL_MODE**:
 *   Encodes the schema as a tool/function definition and passes it through
 *   the provider's tool-calling mechanism. Falls back to JSON_MODE if the
 *   provider does not support the `'tools'` capability.
 *
 * In both modes the generator retries **once** if the first response is
 * not valid JSON, sending a follow-up message that asks the model to
 * correct its output.
 */
final class ObjectGenerator
{
    /**
     * @param ProviderInterface $provider The LLM provider to use
     */
    public function __construct(
        private readonly ProviderInterface $provider,
    ) {
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Generate a structured object from the provider.
     *
     * @param GenerateObjectOptions $options Generation options (schema required)
     *
     * @return ObjectResponse The validated, parsed response
     *
     * @throws \LLMesh\Core\Exceptions\ValidationException On parse / validation failure after retry
     * @throws \LLMesh\Core\Exceptions\ValidationException If options are invalid
     */
    public function generate(GenerateObjectOptions $options): ObjectResponse
    {
        $options->validate();

        $effectiveMode = $this->resolveMode($options);

        return match ($effectiveMode) {
            OutputMode::TOOL_MODE => $this->generateViaToolMode($options),
            OutputMode::JSON_MODE => $this->generateViaJsonMode($options),
        };
    }

    // -------------------------------------------------------------------------
    // Mode resolution
    // -------------------------------------------------------------------------

    /**
     * Resolve the effective mode.
     *
     * TOOL_MODE is downgraded to JSON_MODE if the provider doesn't support tools.
     */
    private function resolveMode(GenerateObjectOptions $options): OutputMode
    {
        if ($options->mode === OutputMode::TOOL_MODE && !$this->provider->supports('tools')) {
            return OutputMode::JSON_MODE;
        }

        return $options->mode;
    }

    // -------------------------------------------------------------------------
    // JSON_MODE
    // -------------------------------------------------------------------------

    /**
     * Run in JSON_MODE: inject schema into system prompt, call chat(), retry once.
     */
    private function generateViaJsonMode(GenerateObjectOptions $options): ObjectResponse
    {
        /** @var SchemaInterface $schema */
        $schema = $options->schema;

        $systemPrompt = $this->buildJsonModeSystemPrompt($schema, $options->system);
        $messages     = $this->buildMessages($options);
        $providerOpts = $this->buildProviderOptions($options, $systemPrompt);

        // --- First attempt ---
        $response     = $this->provider->chat($messages, $providerOpts);
        $responseText = $response->getText();

        try {
            return ObjectResponse::fromJson(
                $responseText,
                $schema,
                $response->getUsage(),
                $response->getRaw(),
            );
        } catch (ValidationException $firstException) {
            // --- Retry once ---
            $retryMessages   = $this->buildRetryMessages($messages, $responseText);
            $retryResponse   = $this->provider->chat($retryMessages, $providerOpts);
            $retryText       = $retryResponse->getText();

            return ObjectResponse::fromJson(
                $retryText,
                $schema,
                $retryResponse->getUsage(),
                $retryResponse->getRaw(),
            );
        }
    }

    // -------------------------------------------------------------------------
    // TOOL_MODE
    // -------------------------------------------------------------------------

    /**
     * Run in TOOL_MODE: encode the schema as a tool call.
     */
    private function generateViaToolMode(GenerateObjectOptions $options): ObjectResponse
    {
        /** @var SchemaInterface $schema */
        $schema = $options->schema;

        $tool = [
            'name'        => 'extract_structured_data',
            'description' => 'Extract structured data matching the provided JSON Schema.',
            'parameters'  => $schema->toArray(),
        ];

        $messages     = $this->buildMessages($options);
        $providerOpts = $this->buildProviderOptions($options, $options->system);
        $providerOpts['tools']       = [$tool];
        $providerOpts['tool_choice'] = ['type' => 'function', 'function' => ['name' => 'extract_structured_data']];

        $response = $this->provider->chat($messages, $providerOpts);
        $raw      = $response->getRaw();

        // Extract tool call arguments from the response
        $toolArgs = $this->extractToolArguments($response, $raw);

        // Validate using the schema
        $parsed = json_decode($toolArgs, associative: true, flags: JSON_THROW_ON_ERROR);
        $validator = new \LLMesh\Core\Schema\SchemaValidator();
        $errors    = $validator->validate($parsed, $schema->toArray());

        if (!empty($errors)) {
            throw new ValidationException(
                'Tool call response does not match the requested schema: ' . implode('; ', $errors),
                $errors,
            );
        }

        return new ObjectResponse(
            object: $parsed,
            usage: $response->getUsage(),
            raw: $raw,
        );
    }

    /**
     * Extract the JSON arguments string from a tool-use response.
     *
     * Tries a structured path first, then falls back to getText().
     */
    private function extractToolArguments(
        \LLMesh\Core\Contracts\ResponseInterface $response,
        array $raw,
    ): string {
        // OpenAI-style: raw.choices[0].message.tool_calls[0].function.arguments
        $openAiArgs = $raw['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? null;
        if ($openAiArgs !== null) {
            return $openAiArgs;
        }

        // Anthropic-style: raw.content[n].input (encoded as JSON)
        foreach ($raw['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'tool_use') {
                return json_encode($block['input'], JSON_THROW_ON_ERROR);
            }
        }

        // Fallback to text
        return $response->getText();
    }

    // -------------------------------------------------------------------------
    // System prompt builders
    // -------------------------------------------------------------------------

    /**
     * Build the JSON_MODE system prompt, optionally prefixed with the user-supplied system text.
     */
    private function buildJsonModeSystemPrompt(SchemaInterface $schema, ?string $userSystem): string
    {
        $schemaJson = $schema->toJson();

        $instruction = <<<INSTRUCTION
            You must respond with ONLY valid JSON — no prose, no markdown code fences, no explanation.
            Your response must match this JSON Schema exactly:

            {$schemaJson}
            INSTRUCTION;

        if ($userSystem !== null && $userSystem !== '') {
            return $userSystem . "\n\n" . $instruction;
        }

        return $instruction;
    }

    // -------------------------------------------------------------------------
    // Message builders
    // -------------------------------------------------------------------------

    private function buildMessages(GenerateObjectOptions $options): array
    {
        if (!empty($options->messages)) {
            return $options->messages;
        }

        return [Message::user($options->prompt ?? '')];
    }

    /**
     * Build the retry message sequence.
     *
     * Appends the bad response as an assistant message, then adds a corrective
     * user turn asking the model to retry.
     *
     * @param  array  $originalMessages The original messages sent on the first attempt
     * @param  string $badResponse      The raw (invalid) response from the first attempt
     * @return array  Extended message array for the retry call
     */
    private function buildRetryMessages(array $originalMessages, string $badResponse): array
    {
        return array_merge(
            $originalMessages,
            [
                Message::assistant($badResponse),
                Message::user(
                    'Your previous response was not valid JSON or did not match the required schema. '
                    . 'Please respond again with ONLY valid JSON that matches the schema — '
                    . 'no markdown, no explanation.'
                ),
            ],
        );
    }

    // -------------------------------------------------------------------------
    // Provider options
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function buildProviderOptions(GenerateObjectOptions $options, ?string $system): array
    {
        $providerOptions = [];

        if ($system !== null && $system !== '') {
            $providerOptions['system'] = $system;
        }

        if ($options->temperature !== null) {
            $providerOptions['temperature'] = $options->temperature;
        }

        if ($options->maxTokens !== null) {
            $providerOptions['max_tokens'] = $options->maxTokens;
        }

        if (!empty($options->stopSequences)) {
            $providerOptions['stop'] = $options->stopSequences;
        }

        return $providerOptions;
    }
}
