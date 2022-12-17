<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport;

use ApacheBorys\Retry\Entity\Config;
use ApacheBorys\Retry\Entity\Message;
use ApacheBorys\Retry\Interfaces\Transport;
use Memcached;

class MemcachedTransport implements Transport
{
    public const PREFIX = 'RETRY-PHP-LIB-';

    private Memcached $memcached;

    private string $prefix;

    private int $ttlForProcessedMessage;

    private array $unprocessedMessages = [];

    private ?Memcached $memcachedForProcessed;

    public function __construct(
        Memcached $memcached,
        int $ttlForProcessedMessage = 0,
        string $prefix = self::PREFIX,
        ?Memcached $memcachedForProcessed = null
    ) {
        $this->memcached = $memcached;
        $this->ttlForProcessedMessage = $ttlForProcessedMessage;
        $this->prefix = $prefix;
        $this->memcachedForProcessed = $memcachedForProcessed;
    }

    public function send(Message $message): bool
    {
        if ($this->memcached->add($this->prefix . $message->getId(), (string) $message)) {
            $this->unprocessedMessages[] = $message->getId();

            return true;
        }

        return false;
    }

    public function fetchUnprocessedMessages(int $batchSize = -1): ?iterable
    {
        $counter = 0;

        foreach ($this->unprocessedMessages as $unprocessedMessageId) {
            yield $this->memcached->get($unprocessedMessageId);

            if ($batchSize > -1 && $batchSize >= $counter) {
                $counter++;
            } else {
                return;
            }
        }

        $allKeys = $this->memcached->getAllKeys();

        foreach ($allKeys as $key) {
            $tempRawMessage = $this->memcached->get($key);
            $tempMessage = Message::fromArray(json_decode($tempRawMessage, true));

            if (!$tempMessage->getIsProcessed()) {
                yield $tempMessage;
                $counter++;

                if ($batchSize > -1 && $batchSize >= $counter) {
                    $counter++;
                } else {
                    return;
                }
            }
        }
    }

    public function getNextId(\Throwable $exception, Config $config): string
    {
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
        $allProcessedKeys = $this->memcachedForProcessed ? $this->memcachedForProcessed->getAllKeys() : [];
        $qtyAllProcessedKeys = count($allProcessedKeys);
        $allKeys = $this->memcached->getAllKeys();
        $result = [];

        if (!$byStream) {
            if ($qtyAllProcessedKeys < $offset) {
                $iterator = $this->getValuesByKeys($allProcessedKeys, $this->memcachedForProcessed, $limit, $offset);

                foreach ($iterator as $message) {
                    $result[] = $message;
                }

                if (count($result) >= $limit) {
                    return $result;
                }
            }

            $iterator = $this->getValuesByKeys($allKeys, $this->memcached, $limit, $offset, $qtyAllProcessedKeys);

            foreach ($iterator as $message) {
                $result[] = $message;
            }

            return $result;
        } else {
            if ($qtyAllProcessedKeys < $offset) {
                return $this->getValuesByKeys($allProcessedKeys, $this->memcachedForProcessed, $limit, $offset);
            }
            return $this->getValuesByKeys($allKeys, $this->memcached, $limit, $offset, $qtyAllProcessedKeys);
        }
    }

    public function howManyTriesWasBefore(\Throwable $exception, Config $config): int
    {
        $correlationId = $config->getExecutor()->getCorrelationId($exception, $config);
        $max = 0;

        foreach ($this->getMessages(-1, 0, true) as $message) {
            if ($message->getCorrelationId() === $correlationId) {
                $max = max($max, $message->getTryCounter());
            }
        }

        return $max;
    }

    public function markMessageAsProcessed(Message $message): bool
    {
        $message->markAsProcessed();

        if (is_null($this->memcachedForProcessed)) {
            return $this->memcached->set($message->getId(), (string) $message, $this->ttlForProcessedMessage);
        } else {
            $resultFromDeletion = $this->memcached->delete($message->getId());
            $resultFromSet = $this->memcachedForProcessed->set($message->getId(), (string) $message, $this->ttlForProcessedMessage);

            return $resultFromDeletion && $resultFromSet;
        }
    }

    /**
     * Return values by provided keys from provided Memcached instance. If limit will be equal to -1, then it will be until end of keys
     *
     * @return \Generator<array-key, Message>
     */
    private function getValuesByKeys(array $keys, ?Memcached $mc = null, int $limit = 100, int $offset = 0, int $pointer = 0): iterable
    {
        if (is_null($mc)) {
            return;
        }

        foreach ($keys as $key) {
            $pointer++;

            if ($pointer <= $offset) {
                continue;
            }

            if ($limit > -1 && ($pointer > $offset + $limit)) {
                return;
            }

            $tempRawMessage = $mc->get($key);
            $tempMessage = Message::fromArray(json_decode($tempRawMessage, true));

            yield $tempMessage;
        }
    }
}
