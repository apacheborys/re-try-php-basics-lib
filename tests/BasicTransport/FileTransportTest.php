<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Tests;

use ApacheBorys\Retry\BasicTransport\FileTransport;
use ApacheBorys\Retry\Entity\Config;
use ApacheBorys\Retry\Entity\Message;
use ApacheBorys\Retry\Interfaces\Transport;
use PHPUnit\Framework\TestCase;

class FileTransportTest extends TestCase
{
    public const FILE_DB = __DIR__ . DIRECTORY_SEPARATOR . 'fileDb.data';

    private const TEST_CORRELATION_ID = 'correlation-id-2';

    /**
     * @covers FileTransport::send
     * @covers FileTransport::markMessageAsProcessed
     * @covers FileTransport::howManyTriesWasBefore
     * @covers FileTransport::fetchUnprocessedMessages
     */
    public function testFlow(): void
    {
        $ft = new FileTransport(self::FILE_DB);

        $message1 = $this->generateMessage($ft, 'correlation-id-1');

        $ft->send($message1);

        self::assertTrue(file_exists(self::FILE_DB));

        $messages = $this->getMessagesFromFileDb();

        self::assertCount(1, $messages);
        self::assertSame($message1->getId(), $messages[0]->getId());
        self::assertFalse($messages[0]->getIsProcessed());

        $messages[0]->markAsProcessed();
        self::assertTrue($ft->markMessageAsProcessed($messages[0]));

        $messages = $this->getMessagesFromFileDb();
        self::assertCount(1, $messages);
        self::assertSame($message1->getId(), $messages[0]->getId());
        self::assertTrue($messages[0]->getIsProcessed());

        $message2 = $this->generateMessage($ft, self::TEST_CORRELATION_ID);
        $ft->send($message2);
        $message3 = $this->generateMessage($ft, self::TEST_CORRELATION_ID, 1);
        $ft->send($message3);

        $messages = $this->getMessagesFromFileDb();

        self::assertCount(3, $messages);
        self::assertSame($message2->getId(), $messages[1]->getId());
        self::assertSame($message3->getId(), $messages[2]->getId());

        self::assertSame(1, $ft->howManyTriesWasBefore($this->createMock(\Throwable::class), $this->createMockConfig()));

        $ft->markMessageAsProcessed($message2);

        $messages = $this->getMessagesFromFileDb();
        self::assertTrue($messages[1]->getIsProcessed());

        $iterator = $ft->fetchUnprocessedMessages();
        $messages = [];
        foreach ($iterator as $unprocessedMessage) {
            $messages[] = $unprocessedMessage;
        }

        self::assertCount(1, $messages);
        self::assertSame($message3->getId(), $messages[0]->getId());
    }

    public function tearDown(): void
    {
        parent::tearDown();
        unlink(self::FILE_DB);
    }

    /**
     * @return Message[]
     */
    private function getMessagesFromFileDb(): array
    {
        $fileData = explode(PHP_EOL, file_get_contents(self::FILE_DB));
        unset($fileData[count($fileData) - 1]);

        $messages = [];
        foreach ($fileData as $rawMessage) {
            $messages[] = Message::fromArray(json_decode($rawMessage, true));
        }

        return $messages;
    }

    private function generateMessage(FileTransport $ft, string $correlationId, int $tryCounter = 0): Message
    {
        return new Message(
            $ft->getNextId($this->createMock(\Throwable::class), $this->createMock(Config::class)),
            'Unit test',
            $correlationId,
            [],
            $tryCounter,
            false,
            new \DateTimeImmutable(),
            'Fake executor class'
        );
    }

    private function createMockConfig(): Config
    {
        $executor = new FakeExecutor();
        $executor->setCorrelationId(self::TEST_CORRELATION_ID);

        $config = new Config(
            'Test config',
            'FakeException',
            4,
            [],
            $this->createMock(Transport::class),
            $executor
        );

        return $config;
    }
}
