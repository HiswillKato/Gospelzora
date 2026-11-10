<?php
declare(strict_types=1);

namespace App\core;

use mysqli;
use RuntimeException;

/** Lazy MySQLi connection provider. */
final class Database
{
    private ?mysqli $connection = null;
    private bool $unavailable = false;

    public function __construct(private array $config)
    {
    }

    public function connection(): mysqli
    {
        if ($this->connection instanceof mysqli) {
            return $this->connection;
        }

        if ($this->unavailable) {
            throw new RuntimeException('Technical difficulties: the database is unavailable. Please try again shortly.');
        }

        $driver = new \mysqli_driver();
        $previousReporting = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $connection = @new mysqli(
                (string)($this->config['host'] ?? '127.0.0.1'),
                (string)($this->config['user'] ?? ''),
                (string)($this->config['password'] ?? ''),
                (string)($this->config['name'] ?? ''),
                (int)($this->config['port'] ?? 3306)
            );
        } finally {
            mysqli_report($previousReporting);
        }

        if ($connection->connect_errno) {
            $this->unavailable = true;
            $message = $connection->connect_error ?: 'Unknown database connection error';
            $code = (int)$connection->connect_errno;
            $connection->close();
            throw new RuntimeException(
                'Technical difficulties: the database is unavailable. Please try again shortly. ' . $message,
                $code
            );
        }

        $charset = (string)($this->config['charset'] ?? 'utf8mb4');
        if (!$connection->set_charset($charset)) {
            $message = $connection->error ?: 'Unable to set database character set';
            $connection->close();
            throw new RuntimeException($message);
        }

        $this->connection = $connection;
        return $this->connection;
    }

    public function close(): void
    {
        if ($this->connection instanceof mysqli) {
            $this->connection->close();
            $this->connection = null;
        }
    }
}
