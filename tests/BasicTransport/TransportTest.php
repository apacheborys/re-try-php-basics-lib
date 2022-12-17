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

    private function testFlow(TestTransportInterface $testForTransport): void
    {
        $transportClassName = get_class($testForTransport->getTransport());
        $message1 = $this->generateMessage($testForTransport->getTransport(), 'correlation-id-1');

        /**
         * Send first message
         */
        $messages = $this->sendFirstMessage($testForTransport, $message1, $transportClassName);

        /**
         * Mark first message as processed
         */
        $messages[0]->markAsProcessed();
        self::assertTrue(
            $testForTransport->getTransport()->markMessageAsProcessed($messages[0]),
            sprintf('Can\'t mark message id %s as processed for %s transport', $message1->getId(), $transportClassName)
        );

        $this->checkTotalQuantityAndState($testForTransport, $message1, $transportClassName);

        /**
         * Send two more messages
         */
        $message2 = $this->generateMessage($testForTransport->getTransport(), self::TEST_CORRELATION_ID);
        $testForTransport->getTransport()->send($message2);
        $message3 = $this->generateMessage($testForTransport->getTransport(), self::TEST_CORRELATION_ID, 1);
        $testForTransport->getTransport()->send($message3);

        /**
         * Check total quantity in database and ids of messages from database
         */
        $this->checkTotalQuantityAndIds($testForTransport, [$message2, $message3], $transportClassName);

        /**
         * How many tries was before for specified correlation id in beginning of this class @see self::TEST_CORRELATION_ID
         */
        self::assertSame(
            1,
            $testForTransport->getTransport()->howManyTriesWasBefore($this->createMock(\Throwable::class),
            $this->createMockConfig())
        );

        /**
         * Mark second message as processed
         */
        $testForTransport->getTransport()->markMessageAsProcessed($message2);

        $messages = $testForTransport->getMessagesFromDb();
        self::assertTrue(
            $messages[1]->getIsProcessed(),
            sprintf('Sent message id %s for %s have incorrect state', $message2->getId(), $transportClassName)
        );

        /**
         * Try to fetch all remaining unprocessed messages
         */
        $this->fetchRemainingUnprocessedMessages($testForTransport, $message3, $transportClassName);
    }

    /**
     * @return Message[]
     */
    private function sendFirstMessage(TestTransportInterface $testForTransport, Message $generatedMessage, string $transportClassName): array
    {
        $testForTransport->getTransport()->send($generatedMessage);

        self::assertTrue($testForTransport->isDatabaseExists(), sprintf('Database for %s is not exists', $transportClassName));

        $messages = $testForTransport->getMessagesFromDb();

        self::assertCount(
            1,
            $messages,
            sprintf('Sent message id %s for %s is not in the database', $generatedMessage->getId(), $transportClassName)
        );
        self::assertSame(
            $generatedMessage->getId(),
            $messages[0]->getId(),
            sprintf('Sent message for %s have incorrect id', $transportClassName)
        );
        self::assertFalse(
            $messages[0]->getIsProcessed(),
            sprintf('Sent message id %s for %s have incorrect state', $generatedMessage->getId(), $transportClassName)
        );

        return $messages;
    }

    private function checkTotalQuantityAndState(TestTransportInterface $testForTransport, Message $generatedMessage, string $transportClassName): void
    {
        $messages = $testForTransport->getMessagesFromDb();
        self::assertCount(
            1,
            $messages,
            sprintf('Wrong message quantity in %s database', $transportClassName)
        );
        self::assertSame(
            $generatedMessage->getId(),
            $messages[0]->getId(),
            sprintf('Sent message for %s have incorrect id', $transportClassName)
        );
        self::assertTrue(
            $messages[0]->getIsProcessed(),
            sprintf('Sent message id %s for %s have incorrect state', $generatedMessage->getId(), $transportClassName)
        );
    }

    /**
     * @param Message[] $generatedMessages
     */
    private function checkTotalQuantityAndIds(TestTransportInterface $testForTransport, array $generatedMessages, string $transportClassName): void
    {
        $messages = $testForTransport->getMessagesFromDb();
        self::assertCount(
            3,
            $messages,
            sprintf('Messages quantity in database for %s is wrong', $transportClassName)
        );
        self::assertSame(
            $generatedMessages[0]->getId(),
            $messages[1]->getId(),
            sprintf('Sent message for %s have incorrect id', $transportClassName)
        );
        self::assertSame(
            $generatedMessages[1]->getId(),
            $messages[2]->getId(),
            sprintf('Sent message for %s have incorrect id', $transportClassName)
        );
    }

    private function fetchRemainingUnprocessedMessages(TestTransportInterface $testForTransport, Message $generatedMessage, string $transportClassName): void
    {
        $iterator = $testForTransport->getTransport()->fetchUnprocessedMessages();
        $messages = [];
        foreach ($iterator as $unprocessedMessage) {
            $messages[] = $unprocessedMessage;
        }

        self::assertCount(
            1,
            $messages,
            sprintf('Wrong quantity of remaining unprocessed messages for %s transport', $transportClassName)
        );
        self::assertSame(
            $generatedMessage->getId(),
            $messages[0]->getId(),
            sprintf('Remaining unprocessed message has wrong id for %s transport', $transportClassName)
        );
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