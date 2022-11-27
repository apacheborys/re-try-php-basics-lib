<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicExecutor\Tests;

use ApacheBorys\Retry\BasicExecutor\HttpExecutor;
use ApacheBorys\Retry\Entity\Message;
use PHPUnit\Framework\TestCase;

class HttpExecutorTest extends TestCase
{
    public function testHandling(): void
    {
        $executor = new TestableHttpExecutor('http://localhost', 'POST');

        $message = new Message(
            '1',
            'retry-name',
            'correlation-id',
            ['payload' => 'test'],
            2,
            false,
            new \DateTimeImmutable(),
            HttpExecutor::class
        );

        $this->assertTrue($executor->getCurlOptionsForTest($message));
    }
}
