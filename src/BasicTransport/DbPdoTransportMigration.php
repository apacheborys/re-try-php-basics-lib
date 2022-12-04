<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport;

use PDO;

class DbPdoTransportMigration
{
    private PDO $pdo;

    private string $tableName;

    private ?string $dbName;

    public function __construct(PDO $pdo, string $tableName = DbPdoTransport::DEFAULT_TABLE_NAME, ?string $dbName = null)
    {
        $this->pdo = $pdo;
        $this->tableName = $tableName;
        $this->dbName = $dbName;
    }

    public function run(): bool
    {
        $sql = "CREATE TABLE " . $this->compileDbAndTableName() . " (
            id CHAR(36),
            retry_name VARCHAR(255),
            correlation_id VARCHAR(255),
            payload TEXT,
            try_counter SMALLINT,
            is_processed TINYINT,
            should_be_executed_at DATETIME,
            executor VARCHAR(1023)
        );";
        $st = $this->pdo->prepare($sql);

        return $st->execute();
    }

    public function rollback(): bool
    {
        $sql = "DROP TABLE IF EXISTS " . $this->compileDbAndTableName() . ";";
        $st = $this->pdo->prepare($sql);

        return $st->execute();
    }

    private function compileDbAndTableName(): string
    {
        return is_null($this->dbName) ? $this->tableName : $this->dbName . '.' . $this->tableName;
    }
}
