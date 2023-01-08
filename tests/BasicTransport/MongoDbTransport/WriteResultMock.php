<?php

namespace ApacheBorys\Retry\BasicTransport\Tests\MongoDbTransport;

class WriteResultMock
{
    private int $insertedCount;

    private int $modifiedCount;

    public function __construct(int $insertedCount = 0, int $modifiedCount = 0)
    {
        $this->insertedCount = $insertedCount;
        $this->modifiedCount = $modifiedCount;
    }

    public function getInsertedCount(): int
    {
        return $this->insertedCount;
    }

    public function getModifiedCount(): int
    {
        return $this->modifiedCount;
    }
}
