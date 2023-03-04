<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Migration;

class Migrator
{
    public function checkAndExecuteMigrations(array $context, string $transportClass)
    {
        $migrations = $this->getAllMigrationsForTransport($context, $transportClass);

        foreach ($migrations as $migration) {
            if (!$migration->wasExecuted()) {
                $migration->run();
            }
        }
    }

    /**
     * @return Migration[]
     */
    private function getAllMigrationsForTransport(array $context, string $transportClass): array
    {
        $migrations = [];

        foreach (get_declared_classes() as $className) {
            if (in_array(Migration::class, class_implements($className))) {
                $migrations[] = $className;
            }
        }

        $migrations = array_map(
            static function (string $migration) use ($context) {
                return new $migration(...$context);
            },
            $migrations
        );

        /** @var Migration[] $migrations */
        $migrations = array_filter(
            $migrations,
            static function (Migration $migration) use ($transportClass) {
                return in_array($transportClass, $migration->support());
            }
        );

        $result = [];
        foreach ($migrations as $migration) {
            $result[$migration->version()] = $migration;
        }

        ksort($result);

        return $result;
    }
}
