<?php

declare(strict_types=1);

namespace LLMesh\Core\Structured\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Description
{
    public function __construct(
        public readonly string $text,
    ) {}
}
