<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Tests;

use ApacheBorys\Retry\BasicTransport\Tests\MongoDbTransport\MongoDbTransportForTest;
use ApacheBorys\Retry\BasicTransport\Tests\MongoDbTransport\MongoManagerMock;
use ApacheBorys\Retry\Entity\Message;
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

    /** @return Message[] */
    public function getMessagesFromDb(): array
    {
        $messages = [];
        foreach ($this->manager->getStorage()['testCollection'] as $item) {
            $item['id'] = $item['_id'];
            unset($item['_id']);

            $messages[] = Message::fromArray($item);
        }

        return $messages;
    }

    public function tearDownAfterClass(): void
    {
    }
}