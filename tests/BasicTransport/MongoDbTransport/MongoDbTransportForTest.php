<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Tests\MongoDbTransport;

use ApacheBorys\Retry\BasicTransport\MongoDbTransport;

class MongoDbTransportForTest extends MongoDbTransport
{
    /** @var MongoManagerMock */
    protected $mongoManager;

    public function __construct(MongoManagerMock $mongoManager, string $namespace)
    {
        $this->mongoManager = $mongoManager;
        $this->namespace = $namespace;
    }
}
