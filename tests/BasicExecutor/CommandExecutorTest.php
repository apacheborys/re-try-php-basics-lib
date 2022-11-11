<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicExecutor\Tests;

use ApacheBorys\Retry\BasicExecutor\CommandExecutor;
use ApacheBorys\Retry\Entity\Message;
use PHPUnit\Framework\TestCase;

class CommandExecutorTest extends TestCase
{
    public function testHandle(): void
    {
        $start = time();
        $executor = new CommandExecutor(__DIR__ . DIRECTORY_SEPARATOR . 'test.php');

        $message = new Message(
            '1',
            'retry-name',
            'correlation-id',
            ['payload' => 'test'],
            2,
            false,
            new \DateTimeImmutable(),
            CommandExecutor::class
        );

        self::expectException(\Exception::class);
        $executor->handle($message);

        $fp = fopen(__DIR__ . DIRECTORY_SEPARATOR . 'execution_report.data', 'w');
        $data = [];
        while ($buffer = fgets($fp)) {
            $data[] = json_decode($buffer);
        }

        fclose($fp);

        self::assertCount(1, $data);
        self::greaterThanOrEqual($datap['']);
    }
}
