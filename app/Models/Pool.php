<?php

namespace App\Models;

use Database\Factories\PoolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pool extends Model
{
    /** @use HasFactory<PoolFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'kind', 'engine', 'runtime_version', 'server_id', 'capacity', 'usage', 'dokploy_ref', 'meta', 'credentials',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'credentials' => 'encrypted:array',
        ];
    }

    /**
     * Admin DSN and SSH details. Secrets live in credentials; host and mode may still be in meta.
     *
     * @return array{
     *     host: ?string,
     *     port: int|string|null,
     *     admin_username: ?string,
     *     admin_password: ?string,
     *     admin_database: ?string,
     *     mode: string,
     *     dokploy_environment_id: ?string,
     *     image: ?string,
     *     ssh_host: ?string,
     *     ssh_port: int|string|null,
     *     ssh_username: ?string,
     *     ssh_private_key: ?string
     * }
     */
    public function adminConnection(): array
    {
        $credentials = $this->credentials ?? [];
        $meta = $this->meta ?? [];

        return [
            'host' => $credentials['host'] ?? $meta['host'] ?? null,
            'port' => $credentials['port'] ?? $meta['port'] ?? null,
            'admin_username' => $credentials['admin_username'] ?? $meta['admin_username'] ?? null,
            'admin_password' => $credentials['admin_password'] ?? $meta['admin_password'] ?? null,
            'admin_database' => $credentials['admin_database'] ?? $meta['admin_database'] ?? null,
            'mode' => (string) ($meta['mode'] ?? 'shared'),
            'dokploy_environment_id' => $credentials['dokploy_environment_id'] ?? $meta['dokploy_environment_id'] ?? null,
            'image' => isset($meta['image']) ? (string) $meta['image'] : null,
            'ssh_host' => $credentials['ssh_host'] ?? null,
            'ssh_port' => $credentials['ssh_port'] ?? 22,
            'ssh_username' => $credentials['ssh_username'] ?? null,
            'ssh_private_key' => $credentials['ssh_private_key'] ?? null,
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
