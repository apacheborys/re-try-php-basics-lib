<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicExecutor;

use ApacheBorys\Retry\BasicExecutor\ValueObject\HttpMethod;
use ApacheBorys\Retry\BasicExecutor\ValueObject\HttpUrl;
use ApacheBorys\Retry\Entity\Config;
use ApacheBorys\Retry\Entity\Message;
use ApacheBorys\Retry\Interfaces\Executor;
use Exception;

class HttpExecutor implements Executor
{
    private const CID = 'RETRY_PHP_CORRELATION_ID';
    private const CONFIG_NAME = 'Retry php config name';

    private HttpUrl $url;
    private HttpMethod $method;
    private array $curlOptions;

    public function __construct(string $url, string $method, array $curlOptions = [])
    {
        if (!in_array('curl', get_loaded_extensions(), true)) {
            throw new Exception('Curl php extension not found, please install it, before using HttpExecutor');
        }

        $this->url = new HttpUrl($url);
        $this->method = new HttpMethod($method);
        $this->curlOptions = $curlOptions;
    }

    public function handle(Message $message): bool
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, 'Retry PHP library. HttpExecutor');

        switch ((string) $this->method) {
            case HttpMethod::GET:
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                curl_setopt($ch, CURLOPT_URL, $this->url . '?' . http_build_query($message->getPayload()['arguments']));
                break;
            case HttpMethod::POST:
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch,CURLOPT_POSTFIELDS, http_build_query($message->getPayload()['arguments']));
                break;
            default:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
                curl_setopt($ch, CURLOPT_URL, $this->url . '?' . http_build_query($message->getPayload()['arguments']));
                break;
        }

        curl_setopt_array($ch, $message->getPayload()['curlOptions']);
        curl_exec($ch);
        curl_close($ch);

        return true;
    }

    public function compilePayload(\Throwable $exception, Config $config): array
    {
        $basis = [
            CURLOPT_HTTPHEADER => [
                self::CID . ': ' . $this->getCorrelationId($exception, $config),
                self::CONFIG_NAME . ': ' . $config->getName(),
            ],
        ];

        return [
            'curlOptions' => array_merge($basis, $this->curlOptions)
        ];
    }

    public function getCorrelationId(\Throwable $exception, Config $config): string
    {
        $id = $_SERVER['HTTP_' . self::CID] ?? apache_request_headers()[self::CID] ?? null;

        if (!$id) {
            $config->getTransport()->getNextId($exception, $config);
        }

        return (string) $id;
    }
}
