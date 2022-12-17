<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Tests;

use ApacheBorys\Retry\BasicTransport\FileTransport;
use ApacheBorys\Retry\Entity\Message;
use ApacheBorys\Retry\Interfaces\Transport;

/**
 * @covers FileTransport::send
 * @covers FileTransport::markMessageAsProcessed
 * @covers FileTransport::howManyTriesWasBefore
 * @covers FileTransport::fetchUnprocessedMessages
 */
class FileTransportTest implements TestTransportInterface
{
    public const FILE_DB = __DIR__ . DIRECTORY_SEPARATOR . 'fileDb.data';

    public function setUpBeforeClass(): void
    {
    }

    public function getTransport(): Transport
    {
        return new FileTransport(self::FILE_DB);
    }

    public function isDatabaseExists(): bool
    {
        return file_exists(self::FILE_DB);
    }

    /** @inheritDoc */
    public function getMessagesFromDb(): array
    {
        $fileData = explode(PHP_EOL, file_get_contents(self::FILE_DB));
        unset($fileData[count($fileData) - 1]);

        $messages = [];
        foreach ($fileData as $rawMessage) {
            $messages[] = Message::fromArray(json_decode($rawMessage, true));
        }

        return $messages;
    }

    public function tearDownAfterClass(): void
    {
        if (file_exists(self::FILE_DB)) {
            unlink(self::FILE_DB);
        }
    }
}
