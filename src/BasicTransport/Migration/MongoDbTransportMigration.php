<?php

namespace ApacheBorys\Retry\BasicTransport\Migration;

use ApacheBorys\Retry\Entity\Message;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;

class MongoDbTransportMigration implements Migration
{
    private Manager $mongoManager;

    private string $namespace;

    public function __construct(Manager $mongoManager, string $namespace)
    {
        $this->mongoManager = $mongoManager;
        $this->namespace = $namespace;
    }

    public function run(): bool
    {
        $query = new Query(['createIndex' => [Message::ELEM_IS_PROCESSED => 1]], ['name' => 'is_processed']);
        $this->mongoManager->executeQuery($this->namespace, $query);

        $query = new Query(['createIndex' => [Message::ELEM_CORRELATION_ID => 1]], ['name' => 'correlation_id']);
        $this->mongoManager->executeQuery($this->namespace, $query);

        return true;
    }

    public function rollback(): bool
    {
        $query = new Query(['dropIndexes' => ['is_processed', 'correlation_id']]);
        $this->mongoManager->executeQuery($this->namespace, $query);

        return true;
    }
}
