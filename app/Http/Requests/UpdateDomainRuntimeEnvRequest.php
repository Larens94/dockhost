<?php

// UpdateDomainRuntimeEnvRequest.php — Validates APP_ENV / APP_DEBUG / LOG_LEVEL for a domain app.
//
// exports: UpdateDomainRuntimeEnvRequest | authorize(): bool | rules(): array | runtimeEntries(): array | shouldDeploy(): bool
// used_by: app/Http/Controllers/DomainController.php
// rules:   Laravel stacks with Dokploy application only. APP_DEBUG becomes string true/false for Dokploy env.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_runtime_env | Runtime env FormRequest
// message:

namespace App\Http\Requests;

use App\Enums\DomainStack;
use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDomainRuntimeEnvRequest extends FormRequest
{
    use AuthorizesDomainHosting;

    public function authorize(): bool
    {
        if (! $this->userCanMutateDomainHosting()) {
            return false;
        }

        $stack = $this->domainFromRoute()->stack ?? DomainStack::None;

        return $stack->isLaravel();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'app_env' => ['required', 'in:local,staging,production,testing'],
            'app_debug' => ['sometimes', 'boolean'],
            'log_level' => ['required', 'in:debug,info,warning,error'],
            'deploy' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<array{key: string, value: string|null}>
     */
    public function runtimeEntries(): array
    {
        $validated = $this->validated();
        $debug = (bool) ($validated['app_debug'] ?? false);

        return [
            ['key' => 'APP_ENV', 'value' => (string) $validated['app_env']],
            ['key' => 'APP_DEBUG', 'value' => $debug ? 'true' : 'false'],
            ['key' => 'LOG_LEVEL', 'value' => (string) $validated['log_level']],
        ];
    }

    public function shouldDeploy(): bool
    {
        return (bool) ($this->validated('deploy') ?? false);
    }
}
