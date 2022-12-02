<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicExecutor\Tests;

use ApacheBorys\Retry\BasicExecutor\HttpExecutor;
use ApacheBorys\Retry\BasicExecutor\ValueObject\HttpMethod;
use ApacheBorys\Retry\Entity\Message;
use PHPUnit\Framework\TestCase;

class HttpExecutorTest extends TestCase
{
    private const HOST = 'http://localhost';

    /** @dataProvider getDataSet */
    public function testHandling(string $method, array $expectedOptions): void
    {
        $executor = new TestableHttpExecutor(self::HOST, $method);

        $message = new Message(
            '1',
            'retry-name',
            'correlation-id',
            ['payload' => 'test', 'arguments' => ['variable' => 'value']],
            2,
            false,
            new \DateTimeImmutable(),
            HttpExecutor::class
        );

        $this->assertSame($expectedOptions, $executor->getCurlOptionsForTest($message));
    }

    public function getDataSet(): iterable
    {
        yield [
            'method' => HttpMethod::POST,
            'expectedOptions' => [
                CURLOPT_USERAGENT => 'Retry PHP library. HttpExecutor',
                CURLOPT_HTTPHEADER => [
                    'RETRY_PHP_MESSAGE_ID: 1',
                ],
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => 'variable=value',
            ],
        ];

        yield [
            'method' => HttpMethod::GET,
            'expectedOptions' => [
                CURLOPT_USERAGENT => 'Retry PHP library. HttpExecutor',
                CURLOPT_HTTPHEADER => [
                    'RETRY_PHP_MESSAGE_ID: 1',
                ],
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_HTTPGET => true,
                CURLOPT_URL => self::HOST . '?variable=value'
            ],
        ];
    }
}
