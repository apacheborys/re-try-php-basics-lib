<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Tests;

use ApacheBorys\Retry\Entity\Message;
use ApacheBorys\Retry\Interfaces\Transport;

interface TestTransportInterface
{
    public function setUpBeforeClass(): void;

    public function getTransport(): Transport;

    public function isDatabaseExists(): bool;

    /** @return Message[] */
    public function getMessagesFromDb(): array;

    public function tearDownAfterClass(): void;
}
