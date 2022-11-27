<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicExecutor\ValueObject;

use LogicException;

class HttpUrl
{
    private array $parsedUrl;
    private string $originalUrl;

    public function __construct(string $value)
    {
        if (!$this->parsedUrl = parse_url($value)) {
            throw new LogicException(
                sprintf("The provided url %s is malformed, please check and fix it", $value)
            );
        }

        $this->originalUrl = $value;
    }

    public function __toString()
    {
        return $this->originalUrl;
    }
}
