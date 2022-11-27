<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicExecutor\ValueObject;

use LogicException;

class HttpMethod
{
    public const GET = 'GET';
    public const HEAD = 'HEAD';
    public const POST = 'POST';
    public const PUT = 'PUT';
    public const DELETE = 'DELETE';
    public const CONNECT = 'CONNECT';
    public const OPTIONS = 'OPTIONS';
    public const TRACE = 'TRACE';
    public const PATCH = 'PATCH';

    private string $value;

    public function __construct(string $value)
    {
        if (!in_array($value, $this->getAvailableMethods(), true)) {
            throw new LogicException(
                sprintf(
                    "Can't apply %s value for HTTP method. Available methods are %s",
                    $value,
                    implode(', ', $this->getAvailableMethods())
                )
            );
        }

        $this->value = $value;
    }

    public function getAvailableMethods(): array
    {
        return [
            self::GET,
            self::HEAD,
            self::POST,
            self::PUT,
            self::DELETE,
            self::CONNECT,
            self::OPTIONS,
            self::TRACE,
            self::PATCH,
        ];
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
