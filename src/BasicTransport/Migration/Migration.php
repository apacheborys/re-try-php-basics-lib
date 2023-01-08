<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Migration;

interface Migration
{
    public function run(): bool;

    public function rollback(): bool;
}
