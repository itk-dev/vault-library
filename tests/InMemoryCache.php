<?php

namespace ItkDev\Vault\Tests;

use Psr\SimpleCache\CacheInterface;

class InMemoryCache implements CacheInterface
{
    private array $cache = [];

    public function get($key, $default = null): mixed
    {
        return $this->cache[$key] ?? $default;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->cache[$key] = $value;

        return true;
    }

    public function delete($key): bool
    {
        unset($this->cache[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];

        return true;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has($key): bool
    {
        return isset($this->cache[$key]);
    }
}
