<?php

declare(strict_types=1);

namespace App\Service\Database;

use PDO;
use PDOException;

final class Database
{
    private string $dns;
    private ?PDO $pdo = null;
    private int $lastPing = 0;
    private int $pingInterval;

    public function __construct(string $dns, int $pingInterval = 10)
    {
        $this->dns = $dns;
        $this->pingInterval = $pingInterval;
    }

    public function execute(string $query, array $params = []): int
    {
        $this->ping();
        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        return $statement->rowCount();
    }

    public function fetch(string $query, array $params = []): ?array
    {
        $this->ping();
        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return $row;
        }
        return null;
    }

    public function fetchAll(string $query, array $params = []): iterable
    {
        $this->ping();
        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    public function begin(): void
    {
        $this->ping();
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    public function ping(): void
    {
        if (is_null($this->pdo)) {
            $this->connect();
            return;
        }
        $now = time();
        if ($now <= $this->lastPing + $this->pingInterval) {
            return;
        }
        try {
            $this->pdo->query('SELECT 1');
            $this->lastPing = time();
        } catch (PDOException) {
            $this->connect();
        }
    }

    private function connect(): void
    {
        $this->pdo = new PDO($this->dns);
        $this->pdo->exec('PRAGMA journal_mode = WAL;');
        $this->pdo->exec('PRAGMA foreign_keys = ON;');
        $this->lastPing = time();
    }
}
