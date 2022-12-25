<?php

namespace ApacheBorys\Retry\BasicTransport\Tests;

use ApacheBorys\Retry\BasicTransport\Tests\MemcachedTransport\MemcachedMock;
use ApacheBorys\Retry\BasicTransport\Tests\MemcachedTransport\MemcachedTransportForTest;
use ApacheBorys\Retry\Entity\Message;
use ApacheBorys\Retry\Interfaces\Transport;

class MemcachedTransportTest implements TestTransportInterface
{
    private MemcachedMock $memcached;

    public function __construct()
    {
        $this->memcached = new MemcachedMock();
    }

    public function setUpBeforeClass(): void
    {
    }

    public function getTransport(): Transport
    {
        return new MemcachedTransportForTest($this->memcached);
    }

    public function isDatabaseExists(): bool
    {
        return true;
    }

    public function getMessagesFromDb(): array
    {
        $result = [];

        foreach ($this->memcached->getAllKeys() as $key) {
            $result[] = Message::fromArray(json_decode($this->memcached->get($key), true));
        }

        return $result;
    }

    public function tearDownAfterClass(): void
    {
    }
}