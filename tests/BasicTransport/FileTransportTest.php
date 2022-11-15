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

        $message1 = $this->generateMessage($ft);

        $ft->send($message1);

        self::assertTrue(file_exists(self::FILE_DB));

        $messages = $this->getMessagesFromFileDb();

        self::assertCount(1, $messages);
        self::assertSame($message1->getId(), $messages[0]->getId());
    }

    /**
     * @return Message[]
     */
    private function getMessagesFromFileDb(): array
    {
        $fileData = explode(PHP_EOL, file_get_contents(self::FILE_DB));

        $messages = [];
        foreach ($fileData as $rawMessage) {
            $messages[] = Message::fromArray(json_decode($rawMessage, true));
        }

        return $messages;
    }

    private function generateMessage(FileTransport $ft): Message
    {
        return new Message(
            $ft->getNextId($this->createMock(\Throwable::class), $this->createMock(Config::class)),
            'Unit test',
            'correlation-id',
            [],
            0,
            false,
            new \DateTimeImmutable(),
            'Fake executor class'
        );
    }
}
