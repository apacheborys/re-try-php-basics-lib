<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport;

use ApacheBorys\Retry\BasicTransport\Tests\MongoDbTransport\BulkWriteMock;
use ApacheBorys\Retry\BasicTransport\Tests\MongoDbTransport\MongoManagerMock;
use ApacheBorys\Retry\BasicTransport\Tests\MongoDbTransport\QueryMock;
use ApacheBorys\Retry\Entity\Config;
use ApacheBorys\Retry\Entity\Message;
use ApacheBorys\Retry\Interfaces\Transport;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;

class MongoDbTransport implements Transport
{
    use UuidGenerator;

    /** @var Manager|MongoManagerMock */
    protected $mongoManager;

    protected string $namespace;

    public function __construct(Manager $mongoManager, string $namespace)
    {
        $this->mongoManager = $mongoManager;
        $this->namespace = $namespace;
    }

    public function send(Message $message): bool
    {
        $bulk = $this->getNewBulkWrite();

        $doc = json_decode((string) $message, true);
        $doc['_id'] = $doc[Message::ELEM_ID];
        unset($doc[Message::ELEM_ID]);

        $bulk->insert($doc);

        $result = $this->mongoManager->executeBulkWrite($this->namespace, $bulk);

        return $result->getInsertedCount() === 1;
    }

    public function fetchUnprocessedMessages(int $batchSize = -1): ?iterable
    {
        $query = $this->getNewQuery([Message::ELEM_IS_PROCESSED => false]);
        $cursor = $this->mongoManager->executeQuery($this->namespace, $query);

        foreach ($cursor as $rawMessage) {
            $rawMessage['id'] = $rawMessage['_id'];
            unset($rawMessage['_id']);

            yield Message::fromArray($rawMessage);
        }
    }

    public function getNextId(\Throwable $exception, Config $config): string
    {
        return $this->generateUuidV4();
    }

    public function getMessages(int $limit = 100, int $offset = 0, bool $byStream = false): iterable
    {
        $query = $this->getNewQuery([], ['limit' => $limit, 'skip' => $offset]);
        $cursor = $this->mongoManager->executeQuery($this->namespace, $query);

        $result = [];
        foreach ($cursor as $rawMessage) {
            $message = Message::fromArray($rawMessage);

            if ($byStream) {
                yield $message;
            } else {
                $result[] = $message;
            }
        }

        if (!$byStream) {
            return $result;
        }
    }

    public function howManyTriesWasBefore(\Throwable $exception, Config $config): int
    {
        $query = $this->getNewQuery([Message::ELEM_CORRELATION_ID => $config->getExecutor()->getCorrelationId($exception, $config)]);
        $cursor = $this->mongoManager->executeQuery($this->namespace, $query);

        $result = 0;
        foreach ($cursor as $rawMessage) {
            $result = max($result, $rawMessage[Message::ELEM_TRY_COUNTER]);
        }

        return $result;
    }

    public function markMessageAsProcessed(Message $message): bool
    {
        $message->markAsProcessed();

        $bulk = $this->getNewBulkWrite();
        $bulk->update(
            ['_id' => $message->getId()],
            ['$set' => [Message::ELEM_IS_PROCESSED => true]],
            ['multi' => false, 'upsert' => false]
        );

        $result = $this->mongoManager->executeBulkWrite($this->namespace, $bulk);

        return $result->getModifiedCount() === 1;
    }

    /**
     * @return BulkWrite|BulkWriteMock
     */
    protected function getNewBulkWrite(): object
    {
        return new BulkWrite();
    }

    /**
     * @return Query|QueryMock
     */
    protected function getNewQuery(array $arguments, array $options = []): object
    {
        return new Query($arguments, $options);
    }
}