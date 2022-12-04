<?php

namespace ApacheBorys\Retry\BasicTransport;

use ApacheBorys\Retry\Entity\Config;
use ApacheBorys\Retry\Entity\Message;
use ApacheBorys\Retry\Interfaces\Transport;
use PDO;

class DbPdoTransport implements Transport
{
    public const DEFAULT_TABLE_NAME = 'retry_exchange';

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
        $sql = 'INSERT INTO ' .
            $this->compileDbAndTableName() .
            ' AS e (id, retry_name, correlation_id, payload, try_counter, is_processed, should_be_executed_at, executor' .
            ') VALUES (:id, :retry_name, :correlation_id, :payload, :try_counter, :is_processed, :should_be_executed_at, :executor)';

        $id = $message->getId();
        $retryName = $message->getRetryName();
        $correlationId = $message->getCorrelationId();
        $payload = json_encode($message->getPayload());
        $tryCounter = $message->getTryCounter();
        $isProcessed = $message->getIsProcessed();
        $shouldBeExecutedAt = $message->getShouldBeExecutedAt()->format('c');
        $executor = $message->getExecutor();

        $st = $this->pdo->prepare($sql);
        $st->bindParam(':id', $id);
        $st->bindParam(':retry_name', $retryName);
        $st->bindParam('correlation_id', $correlationId);
        $st->bindParam('payload', $payload);
        $st->bindParam('try_counter', $tryCounter, PDO::PARAM_INT);
        $st->bindParam('is_processed', $isProcessed, PDO::PARAM_BOOL);
        $st->bindParam('should_be_executed_at', $shouldBeExecutedAt);
        $st->bindParam('executor', $executor);

        return $st->execute();
    }

    public function fetchUnprocessedMessages(int $batchSize = -1): ?iterable
    {
        $sql = 'SELECT id, retry_name, correlation_id, payload, try_counter, is_processed, should_be_executed_at, executor FROM ' .
            $this->compileDbAndTableName() . ' AS e WHERE is_processed = 0';

        if ($batchSize > -1) {
            $sql .= ' LIMIT ' . $batchSize;
        }

        $st = $this->pdo->prepare($sql);
        $st->setFetchMode(PDO::FETCH_CLASS, Message::class);

        $atLeastOneRow = false;
        while ($message = $st->fetch()) {
            $atLeastOneRow = true;
            yield $message;
        }

        if (!$atLeastOneRow) {
            return false;
        }
    }

    public function getNextId(\Throwable $exception, Config $config): string
    {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }

    public function getMessages(int $limit = 100, int $offset = 0, bool $byStream = false): iterable
    {
        $sql = 'SELECT id, retry_name, correlation_id, payload, try_counter, is_processed, should_be_executed_at, executor FROM ' .
            $this->compileDbAndTableName() . ' AS e LIMIT ' . $limit . ' OFFSET ' . $offset;

        $st = $this->pdo->prepare($sql);
        $st->setFetchMode(PDO::FETCH_CLASS, Message::class);

        if ($byStream) {
            while ($message = $st->fetch()) {
                yield $message;
            }
        } else {
            return $st->fetchAll();
        }
    }

    public function howManyTriesWasBefore(\Throwable $exception, Config $config): int
    {
        $sql = 'SELECT COUNT(*) FROM ' .
            $this->compileDbAndTableName() . ' AS e WHERE correlation_id = ' .
            $config->getExecutor()->getCorrelationId($exception, $config);

        return $this->pdo->query($sql)->fetchColumn();
    }

    public function markMessageAsProcessed(Message $message): bool
    {
        $sql = 'UPDATE ' . $this->compileDbAndTableName() . ' SET is_processed = 1 WHERE id = :id';

        $id = $message->getId();

        $st = $this->pdo->prepare($sql);
        $st->bindParam('id', $id);

        return $st->execute();
    }

    private function compileDbAndTableName(): string
    {
        return is_null($this->dbName) ? $this->tableName : $this->dbName . '.' . $this->tableName;
    }
}
