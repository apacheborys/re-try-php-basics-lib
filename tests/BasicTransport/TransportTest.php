<?php

namespace ApacheBorys\Retry\BasicTransport\Tests;

use ApacheBorys\Retry\Entity\Config;
use ApacheBorys\Retry\Entity\Message;
use ApacheBorys\Retry\Interfaces\Transport;
use PHPUnit\Framework\TestCase;

class TransportTest extends TestCase
{
    private const TEST_CORRELATION_ID = 'correlation-id-2';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        foreach (self::getAllTransportsForTest() as $transport) {
            $transport->setUpBeforeClass();
        }
    }

    public function testAllTransports(): void
    {
        foreach (self::getAllTransportsForTest() as $transport) {
            $this->testFlow($transport);
        }
    }

    private function testFlow(TestTransportInterface $test): void
    {
        $transportClassName = get_class($test->getTransport());
        $message1 = $this->generateMessage($test->getTransport(), 'correlation-id-1');

        $test->getTransport()->send($message1);

        self::assertTrue($test->isDatabaseExists(), sprintf('Database for %s is not exists', $transportClassName));

        $messages = $test->getMessagesFromDb();

        self::assertCount(
            1,
            $messages,
            sprintf('Sent message id %s for %s is not in the database', $message1->getId(), $transportClassName)
        );
        self::assertSame(
            $message1->getId(),
            $messages[0]->getId(),
            sprintf('Sent message for %s have incorrect id', $transportClassName)
        );
        self::assertFalse(
            $messages[0]->getIsProcessed(),
            sprintf('Sent message id %s for %s have incorrect state', $message1->getId(), $transportClassName)
        );

        $messages[0]->markAsProcessed();
        self::assertTrue(
            $test->getTransport()->markMessageAsProcessed($messages[0]),
            sprintf('Can\'t mark message id %s as processed for %s transport', $message1->getId(), $transportClassName)
        );

        $messages = $test->getMessagesFromDb();
        self::assertCount(1, $messages);
        self::assertSame($message1->getId(), $messages[0]->getId());
        self::assertTrue($messages[0]->getIsProcessed());

        $message2 = $this->generateMessage($test->getTransport(), self::TEST_CORRELATION_ID);
        $test->getTransport()->send($message2);
        $message3 = $this->generateMessage($test->getTransport(), self::TEST_CORRELATION_ID, 1);
        $test->getTransport()->send($message3);

        $messages = $test->getMessagesFromDb();

        self::assertCount(3, $messages);
        self::assertSame($message2->getId(), $messages[1]->getId());
        self::assertSame($message3->getId(), $messages[2]->getId());

        self::assertSame(
            1,
            $test->getTransport()->howManyTriesWasBefore($this->createMock(\Throwable::class),
            $this->createMockConfig())
        );

        $test->getTransport()->markMessageAsProcessed($message2);

        $messages = $test->getMessagesFromDb();
        self::assertTrue($messages[1]->getIsProcessed());

        $iterator = $test->getTransport()->fetchUnprocessedMessages();
        $messages = [];
        foreach ($iterator as $unprocessedMessage) {
            $messages[] = $unprocessedMessage;
        }

        self::assertCount(1, $messages);
        self::assertSame($message3->getId(), $messages[0]->getId());
    }

    private function generateMessage(Transport $ft, string $correlationId, int $tryCounter = 0): Message
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

    /**
     * @return TestTransportInterface[]
     */
    private static function getAllTransportsForTest(): array
    {
        $result = [];

        foreach (get_declared_classes() as $className) {
            if (in_array(TestTransportInterface::class, class_implements($className))) {
                $result[] = $className;
            }
        }

        return array_map(
            static function (string $transportClass) {
                return new $transportClass();
            },
            $result
        );
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        foreach (self::getAllTransportsForTest() as $transport) {
            $transport->tearDownAfterClass();
        }
    }
}