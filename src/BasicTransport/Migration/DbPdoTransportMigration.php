<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Migration;

use ApacheBorys\Retry\BasicTransport\DbPdoTransport;
use PDO;

class DbPdoTransportMigration implements MigrationInterface
{
    private PDO $pdo;

    private string $tableName;

    private string $migrationTableName;

    private ?string $dbName;

    public function __construct(
        PDO $pdo,
        string $tableName = DbPdoTransport::DEFAULT_TABLE_NAME,
        string $migrationTableName = DbPdoTransport::DEFAULT_MIGRATION_TABLE_NAME,
        ?string $dbName = null
    ) {
        $this->pdo = $pdo;
        $this->tableName = $tableName;
        $this->migrationTableName = $migrationTableName;
        $this->dbName = $dbName;
    }

    public function run(): bool
    {
        $sql = sprintf(
            "CREATE TABLE %s (version INT, executedAt DATETIME DEFAULT NOW, PRIMARY KEY (version))",
            $this->compileDbAndTableName($this->migrationTableName)
        );

        $st = $this->pdo->prepare($sql);
        $createMigrationTable = $st->execute();

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
    %s DATETIME,
    PRIMARY KEY (%s, %s)
)",
            $this->compileDbAndTableName($this->tableName),
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
            $this->compileDbAndTableName($this->tableName),
            DbPdoTransport::COLUMN_IS_PROCESSED
        );

        $st = $this->pdo->prepare($sql);
        $createIndexIsProcessed = $st->execute();

        $sql = sprintf(
            "CREATE INDEX idx_correlation_id_%s ON %s (%s)",
            $this->tableName,
            $this->compileDbAndTableName($this->tableName),
            DbPdoTransport::COLUMN_CORRELATION_ID
        );

        $st = $this->pdo->prepare($sql);
        $createIndexCorrelationId = $st->execute();

        $sql = sprintf("INSERT INTO %s (version) VALUES (%d)", $this->compileDbAndTableName($this->migrationTableName), $this->version());

        $st = $this->pdo->prepare($sql);
        $insertMigrationRow = $st->execute();

        return $createMigrationTable && $createTable && $createIndexIsProcessed && $createIndexCorrelationId && $insertMigrationRow;
    }

    public function rollback(): bool
    {
        $sql = "DROP TABLE IF EXISTS " . $this->compileDbAndTableName($this->tableName) . ";";
        $st = $this->pdo->prepare($sql);

        $table = $st->execute();

        $sql = "DROP TABLE IF EXISTS " . $this->compileDbAndTableName($this->migrationTableName) . ";";
        $st = $this->pdo->prepare($sql);

        $migrationTable = $st->execute();

        return $table && $migrationTable;
    }

    public function version(): int
    {
        return 1;
    }

    public static function support(): array
    {
        return [DbPdoTransport::class];
    }

    public function wasExecuted(): bool
    {
        $sql = sprintf(
            "SELECT COUNT(*) FROM %s WHERE version = %d",
            $this->compileDbAndTableName($this->migrationTableName),
            $this->version()
        );

        try {
            $res = $this->pdo->query($sql);

            return $res->fetchColumn() != 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    private function compileDbAndTableName(string $tableName): string
    {
        return is_null($this->dbName) ? $tableName : $this->dbName . '.' . $tableName;
    }
}
