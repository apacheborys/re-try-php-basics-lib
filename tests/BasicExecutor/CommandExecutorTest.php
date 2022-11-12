<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicExecutor\Tests;

use ApacheBorys\Retry\BasicExecutor\CommandExecutor;
use ApacheBorys\Retry\Entity\Message;
use PHPUnit\Framework\TestCase;

class CommandExecutorTest extends TestCase
{
    private const FILE_NAME = __DIR__ . DIRECTORY_SEPARATOR . 'execution_report.data';

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

        $executor->handle($message);

        $fp = fopen(self::FILE_NAME, 'r');
        $data = [];
        while ($buffer = fgets($fp)) {
            $data[] = json_decode($buffer, true);
        }

        fclose($fp);

        self::assertCount(1, $data);
        self::assertTrue((new \DateTime($data[0]['execution time']))->getTimestamp() >= $start);
    }

    public static function tearDownAfterClass(): void
    {
        if (file_exists(self::FILE_NAME)) {
            unlink(self::FILE_NAME);
        }
    }
}
