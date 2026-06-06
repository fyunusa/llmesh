<?php

declare(strict_types=1);

namespace LLMesh\Core\Exceptions;

/**
 * Exception thrown when attempting to retrieve usage information from a stream
 * before it has been completely consumed.
 */
class StreamNotExhaustedException extends LLMeshException
{
}
