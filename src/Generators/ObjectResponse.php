<?php

declare(strict_types=1);

namespace LLMesh\Core\Generators;

use LLMesh\Core\Contracts\UsageInterface;
use LLMesh\Core\Exceptions\ValidationException;
use LLMesh\Core\Schema\SchemaInterface;
use LLMesh\Core\Schema\SchemaValidator;

/**
 * Response from `LLMesh::generateObject()`.
 *
 * Carries the parsed PHP object/array (`object`), the token usage (`usage`),
 * and the raw provider response payload (`raw`).
 */
final readonly class ObjectResponse
{
    /**
     * @param mixed          $object Parsed PHP value (array or scalar) matching the schema
     * @param UsageInterface $usage  Token usage for the generation call
     * @param array          $raw    Raw provider response payload
     */
    public function __construct(
        public mixed $object,
        public UsageInterface $usage,
        public array $raw,
    ) {
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Parse `$json`, validate the result against `$schema`, and return an
     * `ObjectResponse`.
     *
     * @param string          $json   Raw JSON string from the provider
     * @param SchemaInterface $schema Schema to validate against
     * @param UsageInterface  $usage  Token usage to carry forward
     * @param array           $raw    Raw provider response payload
     *
     * @throws ValidationException If the JSON is malformed or fails schema validation
     */
    public static function fromJson(
        string $json,
        SchemaInterface $schema,
        UsageInterface $usage,
        array $raw,
    ): self {
        // --- 1. Strip markdown code fences that some models wrap JSON in ---
        $stripped = self::stripCodeFences($json);

        // --- 2. Decode JSON ---
        try {
            $decoded = json_decode($stripped, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ValidationException(
                'Provider response is not valid JSON: ' . $e->getMessage(),
                ['json' => $stripped],
                previous: $e,
            );
        }

        // --- 3. Validate against schema ---
        $validator = new SchemaValidator();
        $errors    = $validator->validate($decoded, $schema->toArray());

        if (!empty($errors)) {
            throw new ValidationException(
                'Provider response does not match the requested schema: ' . implode('; ', $errors),
                $errors,
            );
        }

        return new self(
            object: $decoded,
            usage: $usage,
            raw: $raw,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Remove optional leading/trailing markdown code fences.
     *
     * Handles both:
     *   ```json\n{...}\n```
     *   ```\n{...}\n```
     *   {bare JSON}
     *
     * No regex is used for the JSON content itself — only the fence markers
     * are stripped, and `json_decode` performs the actual parsing.
     */
    private static function stripCodeFences(string $text): string
    {
        $trimmed = trim($text);

        // Detect opening fence: ``` optionally followed by a language tag
        if (str_starts_with($trimmed, '```')) {
            $firstNewline = strpos($trimmed, "\n");

            if ($firstNewline !== false) {
                // Drop the opening fence line
                $afterFence = substr($trimmed, $firstNewline + 1);

                // Drop the closing fence if present
                if (str_ends_with(rtrim($afterFence), '```')) {
                    $afterFence = substr($afterFence, 0, strrpos(rtrim($afterFence), '```'));
                }

                return trim($afterFence);
            }
        }

        return $trimmed;
    }
}
