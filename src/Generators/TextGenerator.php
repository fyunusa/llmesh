<?php

declare(strict_types=1);

namespace LLMesh\Core\Generators;

use LLMesh\Core\Contracts\MemoryStoreInterface;
use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Data\Message;
use LLMesh\Core\Data\MessageRole;
use LLMesh\Core\Data\ToolCall;
use LLMesh\Core\Memory\MemoryMessageBuilder;
use LLMesh\Core\Tools\Tool;
use LLMesh\Core\Tools\ToolCallExtractor;
use LLMesh\Core\Tools\ToolExecutor;
use LLMesh\Core\Tools\ToolResult;

/**
 * Generates text using a provider, with optional multi-step tool-use loop.
 *
 * When `GenerateTextOptions::$tools` contains `Tool` instances and the
 * provider returns `finishReason === 'tool_calls'`, the generator will:
 *   1. Extract `ToolCall[]` from the raw response
 *   2. Execute each via `ToolExecutor` (firing `onToolCall` callback first)
 *   3. Append the assistant message and tool-result messages to the conversation
 *   4. Call the provider again
 *   5. Repeat until `finishReason !== 'tool_calls'` OR `maxSteps` is reached
 */
final class TextGenerator
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
     * Generate text based on the provided options.
     *
     * @param GenerateTextOptions $options Generation options
     * @return TextResponse The generated text response
     */
    public function generate(GenerateTextOptions $options): TextResponse
    {
        $options->validate();

        $builder = new MemoryMessageBuilder();

        $messages = ($options->memory !== null && $options->sessionId !== null)
            ? $builder->build($options->sessionId, $options->prompt ?? '', $options->memory)
            : $this->buildMessages($options);

        $providerOpts = $this->buildProviderOptions($options);

        // Separate Tool instances from raw tool definitions so we can execute them
        [$toolObjects, $toolDefs] = $this->separateTools($options->tools);

        // Inject the resolved tool definitions into provider options
        if (!empty($toolDefs)) {
            $providerOpts['tools'] = $toolDefs;
        }

        $executor = new ToolExecutor();
        $step     = 0;

        try {
            $response = $this->provider->chat($messages, $providerOpts);

            // Multi-step tool loop
            while (
                $response->getFinishReason() === 'tool_calls'
                && !empty($toolObjects)
                && $step < $options->maxSteps
            ) {
                $step++;

                // Extract ToolCall DTOs from the raw provider payload
                $toolCalls = ToolCallExtractor::extract($response->getRaw());

                if (empty($toolCalls)) {
                    break; // No parseable tool calls — stop the loop
                }

                // Fire the onToolCall callback before executing each tool
                if ($options->onToolCall !== null) {
                    foreach ($toolCalls as $toolCall) {
                        ($options->onToolCall)($toolCall);
                    }
                }

                // Execute all tool calls; errors are wrapped in ToolResult::error()
                $toolResults = $executor->executeAll($toolCalls, $toolObjects);

                // Append the assistant's tool-call request to the conversation
                $messages[] = $this->buildAssistantToolCallMessage($response, $toolCalls);

                // Append one tool-result message per result
                foreach ($toolResults as $toolResult) {
                    $messages[] = $this->buildToolResultMessage($toolResult);
                }

                // Call the provider again with the extended conversation
                $response = $this->provider->chat($messages, $providerOpts);
            }
        } catch (\Throwable $e) {
            throw $e;
        }

        if ($options->memory !== null && $options->sessionId !== null) {
            $builder->save($options->sessionId, $response->getText(), $options->memory);
        }

        return new TextResponse(
            text: $response->getText(),
            usage: $response->getUsage(),
            finishReason: $response->getFinishReason(),
            raw: $response->getRaw(),
        );
    }

    // -------------------------------------------------------------------------
    // Tool separation
    // -------------------------------------------------------------------------

    /**
     * Split the mixed `$tools` array into:
     *  - `$toolObjects` — `Tool` instances that can be executed locally
     *  - `$toolDefs`    — raw definition arrays (and Tool::toArray() results) for the provider
     *
     * @param  array  $tools  Raw `GenerateTextOptions::$tools` value
     * @return array{0: Tool[], 1: array}
     */
    private function separateTools(array $tools): array
    {
        $toolObjects = [];
        $toolDefs    = [];

        foreach ($tools as $tool) {
            if ($tool instanceof Tool) {
                $toolObjects[] = $tool;
                $toolDefs[]    = $tool->toArray();
            } else {
                // Raw definition array — pass through as-is
                $toolDefs[] = $tool;
            }
        }

        return [$toolObjects, $toolDefs];
    }

    // -------------------------------------------------------------------------
    // Message builders
    // -------------------------------------------------------------------------

    private function buildMessages(GenerateTextOptions $options): array
    {
        if (!empty($options->messages)) {
            return $options->messages;
        }

        return [Message::user($options->prompt ?? '')];
    }

    /**
     * Build the assistant message that carries the tool-call request.
     *
     * @param  \LLMesh\Core\Contracts\ResponseInterface $response  The response that contained tool calls
     * @param  ToolCall[]   $toolCalls The extracted tool calls
     */
    private function buildAssistantToolCallMessage(
        \LLMesh\Core\Contracts\ResponseInterface $response,
        array $toolCalls,
    ): Message {
        // Encode the tool calls as JSON so the provider can see what was requested
        $content = $response->getText() !== ''
            ? $response->getText()
            : json_encode(array_map(fn (ToolCall $tc) => $tc->toArray(), $toolCalls), JSON_THROW_ON_ERROR);

        return Message::assistant($content);
    }

    /**
     * Build a tool-result message from a ToolResult DTO.
     */
    private function buildToolResultMessage(ToolResult $toolResult): Message
    {
        return Message::tool(
            $toolResult->resultToString(),
            $toolResult->toolCallId,
            $toolResult->toolName,
        );
    }

    // -------------------------------------------------------------------------
    // Provider options
    // -------------------------------------------------------------------------

    private function buildProviderOptions(GenerateTextOptions $options): array
    {
        $providerOptions = [];

        if ($options->system) {
            $providerOptions['system'] = $options->system;
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

        // Note: raw tool defs are injected after separateTools() in generate()

        return $providerOptions;
    }
}
