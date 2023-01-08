<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Tests;

use ApacheBorys\Retry\Entity\Config;
use ApacheBorys\Retry\Entity\Message;
use ApacheBorys\Retry\Interfaces\Executor;

class FakeExecutor implements Executor
{
    private string $correlationId;

    public function handle(Message $message): bool
    {
        return true;
    }

    public function compilePayload(\Throwable $exception, Config $config): array
    {
        return [];
    }

    public function setCorrelationId(string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    public function getCorrelationId(\Throwable $exception, Config $config): string
    {
        return $this->correlationId;
    }
}
