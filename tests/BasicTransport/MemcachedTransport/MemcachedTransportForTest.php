<?php
namespace ApacheBorys\Retry\BasicTransport\Tests\MemcachedTransport;

use ApacheBorys\Retry\BasicTransport\MemcachedTransport;

class MemcachedTransportForTest extends MemcachedTransport
{
    /** @var MemcachedMock */
    protected $memcached;

    /** @var MemcachedMock */
    protected $memcachedForProcessed;

    public function __construct(
        MemcachedMock $memcached,
        int $ttlForProcessedMessage = 0,
        string $prefix = self::PREFIX,
        ?MemcachedMock $memcachedForProcessed = null
    ) {
        $this->memcached = $memcached;
        $this->ttlForProcessedMessage = $ttlForProcessedMessage;
        $this->prefix = $prefix;
        $this->memcachedForProcessed = $memcachedForProcessed;
    }
}
