<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicExecutor\Tests;

use ApacheBorys\Retry\BasicTransport\FileTransport;
use ApacheBorys\Retry\Entity\Config;
use ApacheBorys\Retry\Entity\Message;
use PHPUnit\Framework\TestCase;

class FileTransportTest extends TestCase
{
    public const FILE_DB = __DIR__ . DIRECTORY_SEPARATOR . 'fileDb.data';

    public function testFlow(): void
    {
        $ft = new FileTransport(self::FILE_DB);

        $message = new Message(
            $ft->getNextId($this->createMock(\Throwable::class), $this->createMock(Config::class)),
            'Unit test',
            'Some random id',
            [],
            0,
            false,
            new \DateTimeImmutable(),
            'Fake executor class'
        );

        $ft->send($message);

        self::assertTrue(file_exists(self::FILE_DB));
        $fileData = explode(PHP_EOL, file_get_contents(self::FILE_DB));
        self::assertCount(1, $fileData);

        $messages = [];
        foreach ($fileData as $rawMessage) {
            $messages[] = Message::fromArray(json_decode($rawMessage, true));
        }

        self::assertSame($message->getId(), $messages[0]->getId());
    }
}
