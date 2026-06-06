<?php

declare(strict_types=1);

namespace LLMesh\Core\Memory;

use LLMesh\Core\Contracts\MemoryStoreInterface;
use LLMesh\Core\Data\Message;

/**
 * Builds the full message list for a generation request by merging stored
 * history with the incoming user turn, and persists the assistant reply
 * afterwards.
 *
 * `TextGenerator` calls this class internally when both `memory` and
 * `sessionId` are set on `GenerateTextOptions`.
 *
 * @example
 *   $builder  = new MemoryMessageBuilder();
 *   $messages = $builder->build('session-abc', 'Hello!', $store);
 *   // … call provider …
 *   $builder->save('session-abc', $response->getText(), $store);
 */
final class MemoryMessageBuilder
{
    /**
     * Combine stored history with a new user message.
     *
     * Fetches all prior messages from `$store` for `$sessionId`, appends a
     * fresh `Message::user($newUserMessage)` entry, then returns the combined
     * array ready to hand to a provider.
     *
     * @param  string                $sessionId      Conversation session identifier
     * @param  string                $newUserMessage The user's latest input
     * @param  MemoryStoreInterface  $store          The backing memory store
     * @return array<int, Message>   Full message list (history + new user turn)
     */
    public function build(string $sessionId, string $newUserMessage, MemoryStoreInterface $store): array
    {
        $history  = $store->get($sessionId);
        $messages = [];

        // Hydrate persisted history entries back into Message objects
        foreach ($history as $item) {
            if ($item instanceof Message) {
                $messages[] = $item;
            } else {
                $messages[] = new Message(
                    role: \LLMesh\Core\Data\MessageRole::from($item['role']),
                    content: $item['content'],
                    toolCallId: $item['toolCallId'] ?? null,
                    toolName: $item['toolName'] ?? null,
                );
            }
        }

        // Append the new user turn
        $messages[] = Message::user($newUserMessage);

        // Persist the user turn so it is included in subsequent calls
        $store->append($sessionId, Message::user($newUserMessage)->toArray());

        return $messages;
    }

    /**
     * Persist the assistant reply to the memory store after generation.
     *
     * Call this once the provider has returned a successful response.
     *
     * @param string               $sessionId     Conversation session identifier
     * @param string               $assistantReply The model's reply text
     * @param MemoryStoreInterface $store          The backing memory store
     */
    public function save(string $sessionId, string $assistantReply, MemoryStoreInterface $store): void
    {
        $store->append($sessionId, Message::assistant($assistantReply)->toArray());
    }
}
