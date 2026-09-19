<?php

namespace App\Contracts;

/**
 * Stack-agnostic infrastructure driver.
 * Dokploy is the first adapter; Coolify/CapRover/custom can replace it.
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
}
