<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport;

use ApacheBorys\Retry\Entity\Config;
use ApacheBorys\Retry\Entity\Message;
use ApacheBorys\Retry\Interfaces\Transport;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class FileTransport implements Transport
{
    private const MINIMUM_INDEX_TTL = 60;

    private const PREFIX_FOR_TEMP_FILE = 'RE_TRY_FILE_TRANSPORT';

    private string $fileStorage;

    /** Index for messages correlation id -> try counter -> position in the file  */
    private array $fileIndexPosition = [];

    /** Index for messages processed status -> message id -> position in the file */
    private array $fileIndexProcessed = [];

    private ?LoggerInterface $logger;

    /** @var resource */
    private $fp;

    /** @var resource */
    private $fd;

    private int $timeOfCreationIndex = 0;

    public function __construct(string $fileStorage, ?LoggerInterface $logger = null)
    {
        $this->fileStorage = $fileStorage;
        $this->logger = $logger;

        $this->openFileStorage();
        $this->createIndex();
        $this->subscribeToFileStorageChanges();
    }

    public function send(Message $message): bool
    {
        $this->_beforeMethodExec();

        try {
            fseek($this->fp, 0, SEEK_END);
            $position = ftell($this->fp);
            fwrite($this->fp, $message . PHP_EOL);
            fflush($this->fp);
        } catch (\Throwable $e) {
            $this->logError('Writing %s message was failed', [$message->getId()]);
            throw $e;
        }

        $this->addToIndex($message, $position - 1);

        return true;
    }

    public function fetchUnprocessedMessages(int $batchSize = -1): ?iterable
    {
        $this->_beforeMethodExec();

        $returnedItems = 0;

        foreach ($this->fileIndexProcessed[0] as $position) {
            fseek($this->fp, $position + 1);
            $rawMessage = fgets($this->fp);

            $message = Message::fromArray(json_decode($rawMessage, true,512, JSON_THROW_ON_ERROR));

            if (!$message->getIsProcessed()) {
                yield $message;
                $returnedItems++;
            }

            if ($batchSize != -1 && $returnedItems >= $batchSize) {
                return;
            }
        }
    }

    public function getNextId(\Throwable $exception, Config $config): string
    {
        $this->_beforeMethodExec();

        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }

    public function getMessages(int $limit = 100, int $offset = 0, bool $byStream = false): iterable
    {
        $this->_beforeMethodExec();

        fseek($this->fp, 0);

        $result = [];
        $curPos = -1;
        while ($rawMessage = fgets($this->fp)) {
            $curPos++;
            if ($curPos < $offset) {
                continue;
            }

            $message = Message::fromArray(json_decode($rawMessage, true,512, JSON_THROW_ON_ERROR));

            if ($byStream) {
                yield $message;
            } else {
                $result[] = $message;
            }

            if ($curPos >= $offset + $limit) {
                break;
            }
        }

        if (!$byStream) {
            return $result;
        }
    }

    public function howManyTriesWasBefore(\Throwable $exception, Config $config): int
    {
        $this->_beforeMethodExec();

        $correlationId = $config->getExecutor()->getCorrelationId($exception, $config);

        $keysFromIndex = array_keys($this->fileIndexPosition[$correlationId]);

        return isset($this->fileIndexPosition[$correlationId]) && count($keysFromIndex) > 0 ? max($keysFromIndex) : 0;
    }

    public function markMessageAsProcessed(Message $message): bool
    {
        $this->_beforeMethodExec();

        $tempFile = tempnam(sys_get_temp_dir(), self::PREFIX_FOR_TEMP_FILE . '.data');
        $tfp = fopen($tempFile, 'w');

        $position = null;
        fseek($this->fp, 0);
        while ($rawMessage = fgets($this->fp)) {
            $messageFromDb = Message::fromArray(json_decode($rawMessage, true,512, JSON_THROW_ON_ERROR));

            if ($message->getId() === $messageFromDb->getId()) {
                $message->markAsProcessed();
                $position = ftell($tfp);
                fputs($tfp, $message . PHP_EOL);
            } else {
                fputs($tfp, $rawMessage);
            }
        }

        if (is_null($position)) {
            $this->logError(
                'Can\'t find message with id %s, please be sure it was flushed to file storage',
                [$message->getId()]
            );

            fclose($tfp);
            unlink($tempFile);

            return false;
        }

        fclose($tfp);
        /** @psalm-suppress InvalidPropertyAssignmentValue */
        fclose($this->fp);

        unlink($this->fileStorage);
        rename($tempFile, $this->fileStorage);

        $this->openFileStorage();
        $this->addToIndex($message, $position - 1);

        return true;
    }

    public function __destruct()
    {
        $this->unsubscribeToFileStorageChanges();
    }

    private function _beforeMethodExec(): void
    {
        if (get_resource_type($this->fp) != 'stream') {
            $this->openFileStorage();
            $this->createIndex();
        } elseif ($this->isFileStorageChanged()) {
            $this->createIndex();
        }
    }

    private function openFileStorage(): void
    {
        $this->fp = fopen($this->fileStorage, 'a+');
    }

    private function createIndex(): void
    {
        if (time() - $this->timeOfCreationIndex < self::MINIMUM_INDEX_TTL) {
            return;
        }

        fseek($this->fp, 0);

        $previous = 1;
        while ($rawMessage = fgets($this->fp)) {
            $message = Message::fromArray(json_decode($rawMessage, true,512, JSON_THROW_ON_ERROR));
            $this->addToIndex($message, $previous - 1);
            $previous = ftell($this->fp);
        }

        $this->timeOfCreationIndex = time();
    }

    private function subscribeToFileStorageChanges(): void
    {
        if (!in_array('inotify', get_loaded_extensions(), true)) {
            throw new Exception('Inotify php extension not found, please install it, before using FileTransport');
        }

        $this->fd = inotify_init();
        stream_set_blocking($this->fd, false);
    }

    private function isFileStorageChanged(): bool
    {
        return (bool) inotify_read($this->fd);
    }

    private function unsubscribeToFileStorageChanges(): void
    {
        /** @psalm-suppress InvalidPropertyAssignmentValue */
        fclose($this->fd);
    }

    private function addToIndex(Message $message, int $position): void
    {
        $this->fileIndexPosition[$message->getCorrelationId()][$message->getTryCounter()] = $position;

        if ($message->getIsProcessed()
            && isset($this->fileIndexProcessed[0])
            && isset($this->fileIndexProcessed[0][$message->getId()])
        ) {
            unset($this->fileIndexProcessed[0][$message->getId()]);
        }
        $this->fileIndexProcessed[(int) $message->getIsProcessed()][$message->getId()] = $position;
    }

    private function logError(string $message, array $arguments = [], string $level = LogLevel::ERROR): void
    {
        if ($this->logger) {
            $this->logger->{$level}(sprintf($message, ...$arguments));
        }
    }
}