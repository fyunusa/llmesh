<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\Memory;

/**
 * A minimal in-process fake that exposes the same surface as ext-redis / predis
 * used by RedisStore: get(), setex(), del(), exists().
 *
 * @internal
 */
final class FakeRedis
{
    /** @var array<string, string> */
    public array $data = [];

    /** @var array<string, int> */
    public array $ttls = [];

    public bool $throwOnGet = false;

    public function get(string $key): string|false
    {
        if ($this->throwOnGet) {
            throw new \RuntimeException('Connection refused');
        }

        return $this->data[$key] ?? false;
    }

    public function setex(string $key, int $ttl, string $value): void
    {
        $this->data[$key] = $value;
        $this->ttls[$key] = $ttl;
    }

    public function del(string $key): void
    {
        unset($this->data[$key], $this->ttls[$key]);
    }

    public function exists(string $key): int
    {
        return isset($this->data[$key]) ? 1 : 0;
    }
}
