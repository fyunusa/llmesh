<?php

declare(strict_types=1);

namespace LLMesh\Core\Tools;

use LLMesh\Core\Data\ToolCall;

/**
 * Extracts `ToolCall` DTOs from raw provider response payloads.
 *
 * Supports both:
 *   - **OpenAI format**: `choices[0].message.tool_calls[]`
 *   - **Anthropic format**: `content[]` blocks with `type === 'tool_use'`
 *
 * This is intentionally a small, static utility — it has no state and
 * can be called cheaply at each iteration of the tool loop.
 */
final class ToolCallExtractor
{
    /**
     * Extract all tool calls from a raw provider response.
     *
     * Returns an empty array when no tool calls are present.
     *
     * @param  array<string, mixed> $raw
     * @return ToolCall[]
     */
    public static function extract(array $raw): array
    {
        // --- OpenAI: choices[0].message.tool_calls ---
        $openAiCalls = $raw['choices'][0]['message']['tool_calls'] ?? null;
        if (is_array($openAiCalls) && !empty($openAiCalls)) {
            return self::fromOpenAi($openAiCalls);
        }

        // --- Anthropic: content[].type === 'tool_use' ---
        $contentBlocks = $raw['content'] ?? null;
        if (is_array($contentBlocks)) {
            $toolUseBlocks = array_filter(
                $contentBlocks,
                fn (array $block) => ($block['type'] ?? '') === 'tool_use',
            );

            if (!empty($toolUseBlocks)) {
                return self::fromAnthropic(array_values($toolUseBlocks));
            }
        }

        return [];
    }

    // -------------------------------------------------------------------------
    // Private parsers
    // -------------------------------------------------------------------------

    /**
     * @param  list<array<string, mixed>> $calls
     * @return ToolCall[]
     */
    private static function fromOpenAi(array $calls): array
    {
        $result = [];

        foreach ($calls as $call) {
            $id        = $call['id'] ?? '';
            $name      = $call['function']['name'] ?? '';
            $argsRaw   = $call['function']['arguments'] ?? '{}';

            // Arguments are JSON-encoded strings in the OpenAI response
            if (is_string($argsRaw)) {
                try {
                    $args = json_decode($argsRaw, associative: true, flags: JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $args = [];
                }
            } else {
                $args = (array) $argsRaw;
            }

            $result[] = new ToolCall($id, $name, $args);
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>> $blocks
     * @return ToolCall[]
     */
    private static function fromAnthropic(array $blocks): array
    {
        $result = [];

        foreach ($blocks as $block) {
            $id    = $block['id'] ?? '';
            $name  = $block['name'] ?? '';
            $input = $block['input'] ?? [];

            $result[] = new ToolCall($id, $name, is_array($input) ? $input : []);
        }

        return $result;
    }
}
