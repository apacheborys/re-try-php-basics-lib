<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Tests;

use ApacheBorys\Retry\BasicTransport\Tests\MongoDbTransport\MongoDbTransportForTest;
use ApacheBorys\Retry\BasicTransport\Tests\MongoDbTransport\MongoManagerMock;
use ApacheBorys\Retry\Interfaces\Transport;

class MongoDbTransportTest implements TestTransportInterface
{
    private MongoManagerMock $manager;

    public function __construct()
    {
        $this->manager = new MongoManagerMock();
    }

    public function setUpBeforeClass(): void
    {
    }

    public function getTransport(): Transport
    {
        return new MongoDbTransportForTest($this->manager, 'testCollection');
    }

    public function isDatabaseExists(): bool
    {
        return true;
    }

    public function getMessagesFromDb(): array
    {
        // TODO: Implement getMessagesFromDb() method.
    }

    public function tearDownAfterClass(): void
    {
        // TODO: Implement tearDownAfterClass() method.
    }
}