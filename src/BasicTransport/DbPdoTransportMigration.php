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
        $sql = sprintf(
            "CREATE TABLE %s (
    %s CHAR(36),
    %s VARCHAR(255),
    %s VARCHAR(255),
    %s TEXT,
    %s SMALLINT,
    %s TINYINT,
    %s DATETIME,
    %s VARCHAR(1023),
    %s DATETIME DEFAULT NOW,
    PRIMARY KEY (%s, %s)
)",
            $this->compileDbAndTableName(),
            DbPdoTransport::COLUMN_ID,
            DbPdoTransport::COLUMN_RETRY_NAME,
            DbPdoTransport::COLUMN_CORRELATION_ID,
            DbPdoTransport::COLUMN_PAYLOAD,
            DbPdoTransport::COLUMN_TRY_COUNTER,
            DbPdoTransport::COLUMN_IS_PROCESSED,
            DbPdoTransport::COLUMN_SHOULD_BE_EXECUTED_AT,
            DbPdoTransport::COLUMN_EXECUTOR,
            DbPdoTransport::COLUMN_CREATED_AT,
            DbPdoTransport::COLUMN_CREATED_AT,
            DbPdoTransport::COLUMN_ID
        );
        $st = $this->pdo->prepare($sql);
        $createTable = $st->execute();

        $sql = sprintf(
            "CREATE INDEX idx_is_process_%s ON %s (%s)",
            $this->tableName,
            $this->compileDbAndTableName(),
            DbPdoTransport::COLUMN_IS_PROCESSED
        );

        $st = $this->pdo->prepare($sql);
        $createIndexIsProcessed = $st->execute();

        $sql = sprintf(
            "CREATE INDEX idx_correlation_id_%s ON %s (%s)",
            $this->tableName,
            $this->compileDbAndTableName(),
            DbPdoTransport::COLUMN_CORRELATION_ID
        );

        $st = $this->pdo->prepare($sql);
        $createIndexCorrelationId = $st->execute();

        return $createTable && $createIndexIsProcessed && $createIndexCorrelationId;
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
