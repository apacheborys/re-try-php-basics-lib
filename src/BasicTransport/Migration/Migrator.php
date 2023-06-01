<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\BasicTransport\Migration;

class Migrator
{
    public function checkAndExecuteMigrations(array $context, string $transportClass): void
    {
        include_once ('DbPdoTransportMigration.php');
        include_once ('MongoDbTransportMigration.php');

        $migrations = $this->getAllMigrationsForTransport($context, $transportClass);

        foreach ($migrations as $migration) {
            if (!$migration->wasExecuted()) {
                $migration->run();
            }
        }
    }

    /**
     * @return MigrationInterface[]
     */
    private function getAllMigrationsForTransport(array $context, string $transportClass): array
    {
        $migrations = [];

        foreach (get_declared_classes() as $className) {
            if (
                in_array(MigrationInterface::class, class_implements($className), true) &&
                in_array($transportClass, $className::support(), true)
            ) {
                $migrations[] = $className;
            }
        }

        /** @var MigrationInterface[] $migrations */
        $migrations = array_map(
            static function (string $migration) use ($context) {
                return new $migration(...$context);
            },
            $migrations
        );

        $result = [];
        foreach ($migrations as $migration) {
            $result[$migration->version()] = $migration;
        }

        ksort($result);

        return $result;
    }
}
