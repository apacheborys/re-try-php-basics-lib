<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Tests\MongoDbTransport;

use MongoDB\Driver\BulkWrite;

class MongoManagerMock
{
    private array $storage = [];

    /**
     * @param BulkWriteMock|BulkWrite $bulkWrite
     */
    public function executeBulkWrite(string $namespace, object $bulkWrite): WriteResultMock
    {
        $action = key($bulkWrite->getActionSnapshot());

        switch ($action) {
            case 'insert':
                return $this->prepareMockForInsert($namespace, $bulkWrite);
            case 'update':
                return $this->prepareMockForUpdate($namespace, $bulkWrite);
            default:
                throw new \Exception('Unsupported action');
        }
    }

    public function executeQuery(string $namespace, QueryMock $query): \Iterator
    {
        $args = $query->getArguments();
        $options = $query->getOptions();

        /** Implement query functionality */
        foreach ($this->storage[$namespace] ?? [] as $pos => $item) {
            if ($this->filterByOptions($options, $pos) && $this->filterByArgs($args, $item)) {
                yield $item;
            }
        }
    }

    public function getStorage(): array
    {
        return $this->storage;
    }

    private function filterByArgs(array $args, array $item): bool
    {
        foreach ($args as $argName => $argValue) {
            if (!isset($item[$argName]) || $item[$argName] !== $argValue) {
                return false;
            }
        }

        return true;
    }

    private function filterByOptions(array $options, int $pos): bool
    {
        if (isset($options['skip']) && $pos < $options['skip']) {
            return false;
        }

        if (isset($options['limit']) && $pos + ($options['skip'] ?? 0) > $options['limit']) {
            return false;
        }

        return true;
    }

    /**
     * @param BulkWriteMock|BulkWrite $bulkWrite
     */
    private function prepareMockForInsert(string $namespace, object $bulkWrite): WriteResultMock
    {
        $this->storage[$namespace] = array_merge($this->storage[$namespace] ?? [], [$bulkWrite->getActionSnapshot()['insert']]);

        return new WriteResultMock(count($bulkWrite->getActionSnapshot()));
    }

    /**
     * @param BulkWriteMock|BulkWrite $bulkWrite
     */
    private function prepareMockForUpdate(string $namespace, object $bulkWrite): WriteResultMock
    {
        $id = $bulkWrite->getActionSnapshot()['update']['id']['_id'];
        $set = $bulkWrite->getActionSnapshot()['update']['set']['$set'];

        $hit = false;
        foreach ($this->storage[$namespace] as &$item) {
            if ($item['_id'] !== $id) {
                continue;
            }

            $hit = true;
            foreach ($set as $key => $setValue) {
                $item[$key] = $setValue;
            }
        }

        return new WriteResultMock(0, (int) $hit);
    }
}
