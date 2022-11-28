<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicExecutor\Tests;

use ApacheBorys\Retry\BasicExecutor\HttpExecutor;
use ApacheBorys\Retry\BasicExecutor\ValueObject\HttpMethod;
use ApacheBorys\Retry\Entity\Message;
use PHPUnit\Framework\TestCase;

class HttpExecutorTest extends TestCase
{
    public function testHandling(string $method, array $expectedOptions): void
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

        $this->assertSame($executor->getCurlOptionsForTest($message));
    }

    public function getDataSet(): iterable
    {
        yield [
            'method' => HttpMethod::POST,
            'expectedOptions' => [
                CURLOPT_USERAGENT => 'Retry PHP library. HttpExecutor',
                10023 => 'RETRY_PHP_MESSAGE_ID: 1',
                42 => true,
                44 => true,
                47 => 1,
                10015 => '',
            ],
        ];
    }
}
