<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Tests;

use ApacheBorys\Retry\Interfaces\Transport;

class MongoDbTransportTest implements TestTransportInterface
{


    public function setUpBeforeClass(): void
    {
        // TODO: Implement setUpBeforeClass() method.
    }

    public function getTransport(): Transport
    {
        // TODO: Implement getTransport() method.
    }

    public function isDatabaseExists(): bool
    {
        // TODO: Implement isDatabaseExists() method.
    }

    /**
     * @inheritDoc
     */
    public function getMessagesFromDb(): array
    {
        // TODO: Implement getMessagesFromDb() method.
    }

    public function tearDownAfterClass(): void
    {
        // TODO: Implement tearDownAfterClass() method.
    }
}