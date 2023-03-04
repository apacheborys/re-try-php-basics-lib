<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Migration;

interface Migration
{
    public function run(): bool;

    public function rollback(): bool;

    public function version(): int;

    /** @return string[] Transport classes what support current migration */
    public function support(): array;

    public function wasExecuted(): bool;
}
