<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport;

use ApacheBorys\Retry\Entity\Config;
use ApacheBorys\Retry\Entity\Message;
use ApacheBorys\Retry\Interfaces\Transport;
use PDO;

class DbPdoTransport implements Transport
{
    use UuidGenerator;

    public const DEFAULT_TABLE_NAME = 'retry_exchange';

    public const COLUMN_ID = Message::ELEM_ID;
    public const COLUMN_RETRY_NAME = Message::ELEM_RETRY_NAME;
    public const COLUMN_CORRELATION_ID = Message::ELEM_CORRELATION_ID;
    public const COLUMN_PAYLOAD = Message::ELEM_PAYLOAD;
    public const COLUMN_TRY_COUNTER = Message::ELEM_TRY_COUNTER;
    public const COLUMN_IS_PROCESSED = Message::ELEM_IS_PROCESSED;
    public const COLUMN_SHOULD_BE_EXECUTED_AT = Message::ELEM_SHOULD_BE_EXECUTED_AT;
    public const COLUMN_EXECUTOR = Message::ELEM_EXECUTOR;
    public const COLUMN_CREATED_AT = 'created_at';

    private const COLUMNS = [
        self::COLUMN_ID,
        self::COLUMN_RETRY_NAME,
        self::COLUMN_CORRELATION_ID,
        self::COLUMN_PAYLOAD,
        self::COLUMN_TRY_COUNTER,
        self::COLUMN_IS_PROCESSED,
        self::COLUMN_SHOULD_BE_EXECUTED_AT,
        self::COLUMN_EXECUTOR,
    ];

    private PDO $pdo;

    private string $tableName;

    private ?string $dbName;

    public function __construct(PDO $pdo, string $tableName = self::DEFAULT_TABLE_NAME, ?string $dbName = null)
    {
        $this->pdo = $pdo;
        $this->tableName = $tableName;
        $this->dbName = $dbName;
    }

    public function send(Message $message): bool
    {
        $sql = sprintf(
            'INSERT INTO %s AS e (%s) VALUES (:%s)',
            $this->compileDbAndTableName(),
            implode(', ', self::COLUMNS),
            implode(', :', self::COLUMNS)
        );

        $id = $message->getId();
        $retryName = $message->getRetryName();
        $correlationId = $message->getCorrelationId();
        $payload = json_encode($message->getPayload());
        $tryCounter = $message->getTryCounter();
        $isProcessed = $message->getIsProcessed();
        $shouldBeExecutedAt = $message->getShouldBeExecutedAt()->format('c');
        $executor = $message->getExecutor();

        $st = $this->pdo->prepare($sql);
        $st->bindParam(self::COLUMN_ID, $id);
        $st->bindParam(self::COLUMN_RETRY_NAME, $retryName);
        $st->bindParam(self::COLUMN_CORRELATION_ID, $correlationId);
        $st->bindParam(self::COLUMN_PAYLOAD, $payload);
        $st->bindParam(self::COLUMN_TRY_COUNTER, $tryCounter, PDO::PARAM_INT);
        $st->bindParam(self::COLUMN_IS_PROCESSED, $isProcessed, PDO::PARAM_BOOL);
        $st->bindParam(self::COLUMN_SHOULD_BE_EXECUTED_AT, $shouldBeExecutedAt);
        $st->bindParam(self::COLUMN_EXECUTOR, $executor);

        return $st->execute();
    }

    public function fetchUnprocessedMessages(int $batchSize = -1): ?iterable
    {
        $sql = sprintf(
            'SELECT %s FROM %s AS e WHERE %s = 0',
            implode(', ', self::COLUMNS),
            $this->compileDbAndTableName(),
            self::COLUMN_IS_PROCESSED
        );

        if ($batchSize > -1) {
            $sql .= ' LIMIT ' . $batchSize;
        }

        $st = $this->pdo->prepare($sql);
        $st->execute();

        $atLeastOneRow = false;
        while ($rawMessage = $st->fetch(PDO::FETCH_ASSOC)) {
            $rawMessage[self::COLUMN_PAYLOAD] = json_decode((string) $rawMessage[self::COLUMN_PAYLOAD], true);
            $message = Message::fromArray($rawMessage);

            $atLeastOneRow = true;
            yield $message;
        }

        if (!$atLeastOneRow) {
            return null;
        }
    }

    public function getNextId(\Throwable $exception, Config $config): string
    {
        return $this->generateUuidV4();
    }

    public function getMessages(int $limit = 100, int $offset = 0, bool $byStream = false): iterable
    {
        $sql = sprintf(
            'SELECT %s FROM %s AS e LIMIT %d OFFSET %d',
            implode(', ', self::COLUMNS),
            $this->compileDbAndTableName(),
            $limit,
            $offset
        );

        $st = $this->pdo->prepare($sql);
        $st->execute();
        $result = [];

        while ($rawMessage = $st->fetch(PDO::FETCH_ASSOC)) {
            $rawMessage[self::COLUMN_PAYLOAD] = json_decode((string) $rawMessage[self::COLUMN_PAYLOAD], true);
            $message = Message::fromArray($rawMessage);

            if ($byStream) {
                yield $message;
            } else {
                $result[] = $message;
            }
        }

        if ($byStream) {
            return $result;
        }
    }

    public function howManyTriesWasBefore(\Throwable $exception, Config $config): int
    {
        $sql = sprintf(
            'SELECT MAX(%s) FROM %s AS e WHERE %s = "%s"',
            self::COLUMN_TRY_COUNTER,
            $this->compileDbAndTableName(),
            self::COLUMN_CORRELATION_ID,
            $config->getExecutor()->getCorrelationId($exception, $config)
        );

        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    public function markMessageAsProcessed(Message $message): bool
    {
        $sql = sprintf(
            'UPDATE %s SET %s = 1 WHERE %s = :%s',
            $this->compileDbAndTableName(),
            self::COLUMN_IS_PROCESSED,
            self::COLUMN_ID,
            self::COLUMN_ID
        );

        $id = $message->getId();

        $st = $this->pdo->prepare($sql);
        $st->bindParam(self::COLUMN_ID, $id);

        return $st->execute();
    }

    private function compileDbAndTableName(): string
    {
        return is_null($this->dbName) ? $this->tableName : $this->dbName . '.' . $this->tableName;
    }
}
