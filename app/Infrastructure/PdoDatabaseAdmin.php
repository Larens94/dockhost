<?php

namespace App\Infrastructure;

use App\Contracts\DatabaseAdmin;
use App\Models\Pool;
use PDO;
use RuntimeException;

class PdoDatabaseAdmin implements DatabaseAdmin
{
    public function exec(Pool $pool, array $statements): void
    {
        $meta = $pool->meta ?? [];
        $username = $meta['admin_username'] ?? null;
        $host = $meta['host'] ?? null;

        if (! $username || ! $host) {
            throw new RuntimeException("Pool {$pool->name} has no admin connection.");
        }

        $engine = strtolower((string) $pool->engine);
        $port = (int) ($meta['port'] ?? (str_starts_with($engine, 'postgres') ? 5432 : 3306));

        $dsn = str_starts_with($engine, 'postgres')
            ? sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $meta['admin_database'] ?? 'postgres')
            : sprintf('mysql:host=%s;port=%d', $host, $port);

        $pdo = new PDO($dsn, (string) $username, (string) ($meta['admin_password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }
}
