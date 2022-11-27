<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicExecutor\Tests;

use ApacheBorys\Retry\BasicExecutor\HttpExecutor;
use ApacheBorys\Retry\Entity\Message;

class TestableHttpExecutor extends HttpExecutor
{
    public function getCurlOptionsForTest(Message $message): array
    {
        return $this->prepareCurlOptions($message);
    }
}
