<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Tests\MongoDbTransport;

class QueryMock
{
    private array $arguments;

    private array $options;

    public function __construct(array $arguments, array $options = [])
    {
        $this->arguments = $arguments;
        $this->options = $options;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
