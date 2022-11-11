<?php
declare(strict_types=1);

$fileName = __DIR__ . DIRECTORY_SEPARATOR . 'execution_report.data';

$fp = fopen($fileName, "a");

$data = [
    'execution time' => (new DateTime())->format('c'),
] + $argv;

fwrite($fp, json_encode($data) . PHP_EOL);
fclose($fp);

throw new Exception('test exception');