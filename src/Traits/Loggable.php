<?php
declare(strict_types=1);

namespace Biblio\Traits;

trait Loggable
{
    public function log(string $message): void
    {
        $logFile = __DIR__ . '/../../bibliotheque.log';
        $date = date('Y-m-d H:i:s');
        $className = static::class;
        $logMessage = sprintf("[%s] [%s] %s\n", $date, $className, $message);
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}