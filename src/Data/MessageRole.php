<?php

declare(strict_types=1);

namespace LLMesh\Core\Data;

/**
 * Enum for message roles in conversations.
 */
enum MessageRole: string
{
    case USER = 'user';
    case ASSISTANT = 'assistant';
    case SYSTEM = 'system';
    case TOOL = 'tool';
}
