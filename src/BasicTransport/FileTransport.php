<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport;

use ApacheBorys\Retry\Entity\Config;
use ApacheBorys\Retry\Entity\Message;
use ApacheBorys\Retry\Interfaces\Transport;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * @method send(Message $message): bool
 * @method getMessages(int $limit = 100, int $offset = 0, bool $byStream = false): iterable
 */
class FileTransport implements Transport
{
    private string $fileStorage;

    private array $fileIndex;

    private ?LoggerInterface $logger;

    /** @var resource */
    private $fp;

    /** @var resource */
    private $fd;

    private int $watchDescriptor;

    private int $timeOfCreationIndex = 0;

    private const MINIMUM_INDEX_TTL = 60;

    public function __construct(string $fileStorage, ?LoggerInterface $logger = null)
    {
        $this->fileStorage = $fileStorage;
        $this->logger = $logger;

        $this->openFileStorage();
        $this->subscribeToFileStorageChanges();
    }

    /**
     * Overlap for methods @see _send, _getMessages
     */
    public function __call($name, $arguments)
    {
        if ($this->isFileStorageChanged()) {
            $this->createIndex();
        }

        $this->{'_' . $name}(...$arguments);
    }

    private function _send(Message $message): bool
    {
        try {
            fseek($this->fp, 0, SEEK_END);
            fwrite($this->fp, $message . PHP_EOL);
            fflush($this->fp);
        } catch (\Throwable $e) {
            $this->logError('Writing %s message was failed', [$message->getId()]);
            throw $e;
        }

        return true;
    }

    public function fetchUnprocessedMessages(int $batchSize = -1): ?iterable
    {
        // TODO: Implement fetchUnprocessedMessages() method.
    }

    public function getNextId(\Throwable $exception, Config $config): string
    {
        // TODO: Implement getNextId() method.
    }

    private function _getMessages(int $limit = 100, int $offset = 0, bool $byStream = false): iterable
    {
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

            if ($curPos > $offset + $limit) {
                break;
            }
        }

        if (!$byStream) {
            return $result;
        }
    }

    public function howManyTriesWasBefore(\Throwable $exception, Config $config): int
    {
        // TODO: Implement howManyTriesWasBefore() method.
    }

    public function markMessageAsProcessed(Message $message): bool
    {
        // TODO: Implement markMessageAsProcessed() method.
    }

    public function __destruct()
    {
        $this->unsubscribeToFileStorageChanges();
    }

    private function openFileStorage(): void
    {
        $this->fp = fopen($this->fileStorage, 'a+');

        $this->createIndex();
    }

    private function createIndex(): void
    {
        if (time() - $this->timeOfCreationIndex < self::MINIMUM_INDEX_TTL) {
            return;
        }

        fseek($this->fp, 0);

        $previous = 0;
        while ($rawMessage = fgets($this->fp)) {
            $message = Message::fromArray(json_decode($rawMessage, true,512, JSON_THROW_ON_ERROR));
            $this->addToIndex($message, $previous);
            $previous = ftell($this->fp);
        }

        $this->timeOfCreationIndex = time();
    }

    private function subscribeToFileStorageChanges(): void
    {
        if (!in_array('inotify', get_loaded_extensions(), true)) {
            throw new \Exception('Inotify php extension not found, please install it, before using FileTransport');
        }

        $this->fd = inotify_init();
        stream_set_blocking($this->fd, false);
        $this->watchDescriptor = inotify_add_watch($this->fd, $this->fileStorage, IN_MODIFY | IN_CLOSE_WRITE);
    }

    private function isFileStorageChanged(): bool
    {
        return (bool) inotify_read($this->fd);
    }

    private function unsubscribeToFileStorageChanges(): void
    {
        inotify_rm_watch($this->fd, $this->watchDescriptor);
        fclose($this->fd);
    }

    private function addToIndex(Message $message, int $position): void
    {
        $this->fileIndex[$message->getId()][$message->getTryCounter()] = $position;
    }

    private function logError(string $message, array $arguments = [], string $level = LogLevel::ERROR): void
    {
        if ($this->logger) {
            $this->logger->{$level}(sprintf($message, ...$arguments));
        }
    }
}