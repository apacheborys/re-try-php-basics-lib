<?php
declare(strict_types=1);

namespace ApacheBorys\Retry\Common;

class WarmUpService
{
    public const FILES_FOR_IGNORE = [
        __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'BasicExecutor' . DIRECTORY_SEPARATOR . 'test.php',
    ];

    public function registerAllClasses(): void
    {
        $srcLocation = __DIR__ . DIRECTORY_SEPARATOR . '..';
        $testsLocation = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'tests';

        $filesForIgnore = array_map(static function (string $path) { return realpath($path); }, self::FILES_FOR_IGNORE);

        $this->loadDir($srcLocation, $filesForIgnore);
        $this->loadDir($testsLocation, $filesForIgnore);
    }

    /**
     * @param string[] $filesForIgnore
     */
    private function loadDir(string $loc, array $filesForIgnore): void
    {
        foreach (scandir($loc) as $item) {
            if ($item === '..' || $item === '.') {
                continue;
            }

            $fullPath = $loc . DIRECTORY_SEPARATOR . $item;

            if (is_dir($fullPath)) {
                $this->loadDir($fullPath, $filesForIgnore);
            }

            if (in_array(realpath($fullPath), $filesForIgnore,true)) {
                continue;
            }

            if (pathinfo($fullPath)['extension'] ?? '' === 'php') {
                require_once $fullPath;
            }
        }
    }
}
