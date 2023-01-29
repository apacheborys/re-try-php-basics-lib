<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicExecutor;

use ApacheBorys\Retry\Entity\Config;
use ApacheBorys\Retry\Entity\Message;
use ApacheBorys\Retry\Interfaces\Executor;
use Psr\Log\LoggerInterface;

class CommandExecutor implements Executor
{
    public const ALIAS_FOR_CORRELATION_ID = 'PHP_RETRY_COMMAND_EXECUTOR';

    private string $commandAddress;
    private array $arguments;
    private array $environmentVars;
    private ?string $cwd;
    private ?array $envVariablesSnapshot = null;
    private ?LoggerInterface $logger;

    /**
     * @param string $commandAddress                    First operand after php
     * @param array<string, string> $arguments          Array of arguments. Key - argument name, value - regular expression for error message
     * @param string|null $cwd                          The initial working dir for the command
     * @param array<String, String> $environmentVars    Set of env variables to execute command. It will be set before execution and rolled back after
     */
    public function __construct(
        string $commandAddress,
        array $arguments = [],
        ?string $cwd = null,
        array $environmentVars = [],
        ?LoggerInterface $logger = null
    ) {
        $this->commandAddress = $commandAddress;
        $this->arguments = $arguments;
        $this->cwd = $cwd;
        $this->environmentVars = $environmentVars;
        $this->logger = $logger;
    }

    public function handle(Message $message): bool
    {
        putenv(self::ALIAS_FOR_CORRELATION_ID . '=' . $message->getCorrelationId());
        $this->saveEnvironmentVariables();

        try {
            $tmpFileName = tempnam(sys_get_temp_dir(), "re-try-command-executor");

            $descriptorSpec = array(
                0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
                1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
                2 => array("file", $tmpFileName, "a") // stderr is a file to write to
            );

            $process = proc_open(
                'php ' .  $this->commandAddress . ' ' . $this->compileArguments($message),
                $descriptorSpec,
                $pipes,
                $this->cwd,
                $this->environmentVars
            );

            if (!is_resource($process)) {
                return false;
            }

            while (true) {
                $statusData = proc_get_status($process);
                if ($statusData['running'] === false) {
                    break;
                }

                sleep(1);
            }

            proc_close($process);
        } catch (\Throwable $e) {
            $this->rollbackEnvironmentVariables();
            if ($this->logger) {
                $this->logger->debug(
                    sprintf(
                        'Command executor in Retry library catch exception during execution of command. Error: %s',
                        $e->getMessage()
                    )
                );
            }

            return false;
        }

        $this->rollbackEnvironmentVariables();
        putenv(self::ALIAS_FOR_CORRELATION_ID);

        return true;
    }

    public function compilePayload(\Throwable $exception, Config $config): array
    {
        $result = [];

        foreach ($this->arguments as $argName => $argRegExp) {
            preg_match($argRegExp, $exception->getMessage(), $matches);
            if ($matches && isset($matches[0])) {
                $result[$argName] = $matches[0];
            }
        }

        return $result;
    }

    public function getCorrelationId(\Throwable $exception, Config $config): string
    {
        $id = getenv(self::ALIAS_FOR_CORRELATION_ID);

        if (!$id) {
            $config->getTransport()->getNextId($exception, $config);
        }

        return (string) $id;
    }

    private function compileArguments(Message $message): string
    {
        $result = '';

        foreach ($message->getPayload() as $argName => $value) {
            $result .= $argName . $value;
        }

        return $result;
    }

    private function saveEnvironmentVariables(): void
    {
        $snapshot = [];

        foreach ($this->environmentVars as $varName => $value) {
            $variable = getenv($varName);

            if ($variable) {
                $snapshot[$varName] = $variable;
            }

            putenv($varName . '=' . $value);
        }

        $this->envVariablesSnapshot = $snapshot;
    }

    private function rollbackEnvironmentVariables(): void
    {
        foreach ($this->envVariablesSnapshot ?? [] as $varName => $value) {
            putenv($varName . '=' . $value);
        }

        $envVarsToUnset = array_diff_assoc($this->environmentVars, $this->envVariablesSnapshot ?? []);

        foreach ($envVarsToUnset as $varName => $value) {
            putenv($varName);
        }
    }
}
