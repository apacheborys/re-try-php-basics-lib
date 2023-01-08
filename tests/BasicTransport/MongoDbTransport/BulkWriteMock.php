<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Tests\MongoDbTransport;

class BulkWriteMock
{
    private array $actionSnapshot = [];

    /**
     * @param string[] $doc
     */
    public function insert(array $doc): void
    {
        $this->actionSnapshot['insert'] = $doc;
    }

    public function update(array $id, array $set, array $options): void
    {
        $this->actionSnapshot['update'] = [
            'id' => $id,
            'set' => $set,
            'options' => $options,
        ];
    }

    /**
     * @return array[]
     */
    public function getActionSnapshot(): array
    {
        return $this->actionSnapshot;
    }
}
