<?php

declare(strict_types=1);

namespace LLMesh\Core\Generators;

/**
 * Output mode for `generateObject`.
 *
 * - `JSON_MODE`  — injects the JSON Schema into the system prompt and instructs
 *                  the model to respond with raw JSON only. Works with all
 *                  providers that support chat.
 *
 * - `TOOL_MODE`  — encodes the schema as a tool/function call and relies on
 *                  native structured output. The provider must support tools.
 *                  Falls back to JSON_MODE if the provider does not support
 *                  the 'tools' capability.
 */
enum OutputMode: string
{
    case JSON_MODE = 'json';
    case TOOL_MODE = 'tool';
}
