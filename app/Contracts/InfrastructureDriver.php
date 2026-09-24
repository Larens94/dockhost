<?php

namespace App\Contracts;

/**
 * Stack-agnostic infrastructure driver.
 * Dokploy is the first adapter; another PaaS can replace it.
 */
interface InfrastructureDriver
{
    public function name(): string;

    /**
     * @return array{ok: bool, message?: string}
     */
    public function ping(): array;

    /**
     * @param  array<string, mixed>  $definition
     * @return array{external_id: string|null, raw?: mixed}
     */
    public function deployCompose(array $definition): array;

    /**
     * @param  array<string, mixed>  $definition
     * @return array{external_id: string|null, raw?: mixed}
     */
    public function deployApplication(array $definition): array;

    /**
     * @param  array<string, mixed>  $definition
     * @return array{ok: bool, raw?: mixed}
     */
    public function attachDomain(array $definition): array;

    /**
     * @param  array<string, mixed>  $definition
     * @return array{ok: bool, external_id: string|null, status: string, raw?: mixed}
     */
    public function createDatabase(array $definition): array;

    /**
     * @param  array<string, mixed>  $definition
     * @return array{ok: bool, raw?: mixed}
     */
    public function updateEnvironment(array $definition): array;

    /**
     * @param  array<string, mixed>  $definition
     * @return array{ok: bool, message?: string, raw?: mixed}
     */
    public function destroyApplication(array $definition): array;

    /**
     * @return array{ok: bool, message?: string, servers: list<array{id: string, name: string, ip: ?string}>}
     */
    public function listServers(): array;

    /**
     * @param  array<string, mixed>  $definition
     * @return array{ok: bool, output: string}
     */
    public function exec(array $definition): array;
}
