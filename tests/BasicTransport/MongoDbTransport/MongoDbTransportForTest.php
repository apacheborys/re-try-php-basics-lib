<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Tests\MongoDbTransport;

use ApacheBorys\Retry\BasicTransport\MongoDbTransport;

class MongoDbTransportForTest extends MongoDbTransport
{
    /** @var MongoManagerMock */
    protected $mongoManager;

    private ?BulkWriteMock $lastBulkWrite = null;

    private ?QueryMock $lastQueryMock = null;

    public function __construct(MongoManagerMock $mongoManager, string $namespace)
    {
        $this->mongoManager = $mongoManager;
        $this->namespace = $namespace;
    }

    protected function getNewBulkWrite(): object
    {
        $this->lastBulkWrite = new BulkWriteMock();
        return $this->lastBulkWrite;
    }

    protected function getNewQuery(array $arguments, array $options = []): object
    {
        $this->lastQueryMock = new QueryMock($arguments, $options);
        return $this->lastQueryMock;
    }

    public function getLastBulkWrite(): ?BulkWriteMock
    {
        return $this->lastBulkWrite;
    }

    public function getLastQueryMock(): ?QueryMock
    {
        return $this->lastQueryMock;
    }
}
