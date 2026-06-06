<?php

declare(strict_types=1);

namespace LLMesh\Core\RAG;

/**
 * Immutable value object representing a piece of content in the RAG pipeline.
 *
 * Documents flow through: load → split → embed → store.
 * Once embedded, a Document carries its float[] vector alongside the text.
 */
final class Document
{
    /**
     * @param string       $id        Unique identifier (auto-generated UUID v4 when not supplied)
     * @param string       $content   Raw text content
     * @param array        $metadata  Arbitrary key-value metadata (source path, page number, etc.)
     * @param float[]|null $embedding Embedding vector, set after the embed step
     */
    public function __construct(
        public readonly string $id,
        public readonly string $content,
        public readonly array $metadata = [],
        public readonly ?array $embedding = null,
    ) {
    }

    // -------------------------------------------------------------------------
    // Named constructors
    // -------------------------------------------------------------------------

    /**
     * Create a Document from plain text, auto-generating a UUID id.
     *
     * @param string $content  The raw text content
     * @param array  $metadata Optional metadata key-value pairs
     * @return self
     */
    public static function fromText(string $content, array $metadata = []): self
    {
        return new self(
            id:       self::generateUuid(),
            content:  $content,
            metadata: $metadata,
        );
    }

    /**
     * Create a Document by reading an entire file.
     *
     * The `source` metadata key is automatically set to the canonical path.
     *
     * @param  string $path Absolute or relative path to the file
     * @return self
     *
     * @throws \RuntimeException When the file cannot be read
     */
    public static function fromFile(string $path): self
    {
        $realPath = realpath($path);

        if ($realPath === false || !is_readable($realPath)) {
            throw new \RuntimeException("Cannot read file: {$path}");
        }

        $content = file_get_contents($realPath);

        if ($content === false) {
            throw new \RuntimeException("Failed to read contents of: {$path}");
        }

        return new self(
            id:       self::generateUuid(),
            content:  $content,
            metadata: [
                'source'    => $realPath,
                'filename'  => basename($realPath),
                'extension' => pathinfo($realPath, PATHINFO_EXTENSION),
            ],
        );
    }

    // -------------------------------------------------------------------------
    // Immutable mutation
    // -------------------------------------------------------------------------

    /**
     * Return a new Document with the given embedding vector attached.
     *
     * The original is not modified (readonly class).
     *
     * @param  float[] $embedding The embedding vector
     * @return self
     */
    public function withEmbedding(array $embedding): self
    {
        return new self(
            id:        $this->id,
            content:   $this->content,
            metadata:  $this->metadata,
            embedding: $embedding,
        );
    }

    // -------------------------------------------------------------------------
    // UUID generator (no external dependency)
    // -------------------------------------------------------------------------

    /**
     * Generate a RFC 4122 UUID v4 using random bytes.
     */
    private static function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0F) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3F) | 0x80); // variant RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
