<?php

namespace ApacheBorys\Retry\BasicTransport\Tests\MemcachedTransport;

class MemcachedMock
{
    private array $storage;

    public function add(string $key, string $value): bool
    {
        $this->storage[$key] = $value;
        return true;
    }

    public function get(string $key): string
    {
        return $this->storage[$key];
    }

    /**
     * @return string[]
     */
    public function getAllKeys(): array
    {
        return array_keys($this->storage);
    }

    public function set(string $key, string $value, int $ttl): bool
    {
        return $this->add($key, $value);
    }

    public function delete(string $key): bool
    {
        unset($this->storage[$key]);
        return true;
    }
}
