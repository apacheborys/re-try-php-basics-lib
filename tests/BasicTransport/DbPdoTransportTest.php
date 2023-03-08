<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Tests;

use ApacheBorys\Retry\BasicTransport\DbPdoTransport;
use ApacheBorys\Retry\BasicTransport\Migration\DbPdoTransportMigration;
use ApacheBorys\Retry\Entity\Message;
use ApacheBorys\Retry\Interfaces\Transport;

class DbPdoTransportTest implements TestTransportInterface
{
    private const DB_FILE_NAME = __DIR__ . DIRECTORY_SEPARATOR . 'temp-sqlite.db';

    private \PDO $pdo;

    public function __construct()
    {
        if (file_exists(self::DB_FILE_NAME)) {
            unlink(self::DB_FILE_NAME);
        }

        $this->pdo = new \PDO('sqlite:' . self::DB_FILE_NAME);
        $this->pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function setUpBeforeClass(): void
    {
    }

    public function getTransport(): Transport
    {
        return new DbPdoTransport($this->pdo);
    }

    public function isDatabaseExists(): bool
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM ' . DbPdoTransport::DEFAULT_TABLE_NAME);
        $st->execute();

        return is_numeric($st->fetchColumn());
    }

    public function getMessagesFromDb(): array
    {
        $st = $this->pdo->prepare('SELECT * FROM ' . DbPdoTransport::DEFAULT_TABLE_NAME);
        $st->execute();

        $result = [];
        while ($rawMessage = $st->fetch(\PDO::FETCH_ASSOC)) {
            $rawMessage[DbPdoTransport::COLUMN_PAYLOAD] = json_decode($rawMessage[DbPdoTransport::COLUMN_PAYLOAD], true);
            $result[] = Message::fromArray($rawMessage);
        }

        return $result;
    }

    public function tearDownAfterClass(): void
    {
        unset($this->pdo);
        unlink(self::DB_FILE_NAME);
    }
}
