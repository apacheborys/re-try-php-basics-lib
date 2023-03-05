<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Migration;

use ApacheBorys\Retry\BasicTransport\MongoDbTransport;
use ApacheBorys\Retry\Entity\Message;
use MongoDB\Driver\Manager;

class MongoDbTransportMigration implements MigrationInterface
{
    private Manager $mongoManager;

    private string $namespace;
    private string $migrationNamespace;

    private MongoDbTransport $mongoDbTransport;

    public function __construct(Manager $mongoManager, string $namespace, string $migrationNamespace, MongoDbTransport $mongoDbTransport)
    {
        $this->mongoManager = $mongoManager;
        $this->namespace = $namespace;
        $this->mongoDbTransport = $mongoDbTransport;
        $this->migrationNamespace = $migrationNamespace;
    }

    public function run(): bool
    {
        $query = $this->mongoDbTransport->getNewQuery(['createIndex' => [Message::ELEM_IS_PROCESSED => 1]], ['name' => 'is_processed']);
        $this->mongoManager->executeQuery($this->namespace, $query);

        $query = $this->mongoDbTransport->getNewQuery(['createIndex' => [Message::ELEM_CORRELATION_ID => 1]], ['name' => 'correlation_id']);
        $this->mongoManager->executeQuery($this->namespace, $query);

        $doc = [
            '_id' => $this->version(),
            'executed' => new \MongoDate(),
        ];

        $bulkWrite = $this->mongoDbTransport->getNewBulkWrite();

        /** @psalm-suppress InvalidScalarArgument */
        $bulkWrite->insert($doc);
        $result = $this->mongoManager->executeBulkWrite($this->migrationNamespace, $bulkWrite);

        return $result->getInsertedCount() === 1;
    }

    public function rollback(): bool
    {
        $query = $this->mongoDbTransport->getNewQuery(['dropIndexes' => ['is_processed', 'correlation_id']]);
        $this->mongoManager->executeQuery($this->namespace, $query);

        $bulkWrite = $this->mongoDbTransport->getNewBulkWrite();
        $bulkWrite->delete([]);
        $this->mongoManager->executeBulkWrite($this->namespace, $bulkWrite);

        $bulkWrite = $this->mongoDbTransport->getNewBulkWrite();
        $bulkWrite->delete([]);
        $this->mongoManager->executeBulkWrite($this->migrationNamespace, $bulkWrite);

        return true;
    }

    public function version(): int
    {
        return 1;
    }

    public function support(): array
    {
        return [MongoDbTransport::class];
    }

    public function wasExecuted(): bool
    {
        $query = $this->mongoDbTransport->getNewQuery(['_id' => $this->version()]);
        $cursor = $this->mongoManager->executeQuery($this->migrationNamespace, $query);

        return count($cursor->toArray()) != 0;
    }
}
