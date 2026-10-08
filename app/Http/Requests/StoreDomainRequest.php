<?php

// StoreDomainRequest.php — StoreDomainRequest module.
//
// exports: StoreDomainRequest | StoreDomainRequest::authorize(): bool | StoreDomainRequest::rules(): array | StoreDomainRequest::messages(): array | StoreDomainRequest::withValidator(Validator $validator): void
// used_by: app/Http/Controllers/DomainController.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace App\Http\Requests;

use App\Enums\DatabaseEngine;
use App\Enums\DomainStack;
use App\Models\Infrastructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fqdn' => ['required', 'string', 'max:255', 'unique:domains,fqdn'],
            'create_database' => ['sometimes', 'boolean'],
            'engine' => ['required_if:create_database,true', 'nullable', Rule::enum(DatabaseEngine::class)],
            'infra_slug' => ['required', 'string', 'max:64', Rule::exists('infrastructures', 'slug')],
            'stack' => ['required', Rule::enum(DomainStack::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'infra_slug.required' => __('panel.validation.infra_required'),
            'infra_slug.exists' => __('panel.validation.infra_panel_only'),
            'stack.required' => __('panel.validation.stack_required'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $infrastructure = Infrastructure::query()
                ->where('slug', $this->input('infra_slug'))
                ->first();

            if (! $infrastructure instanceof Infrastructure) {
                return;
            }

            if (! $infrastructure->isPanelManaged() || ! $infrastructure->canHostDomains()) {
                $validator->errors()->add(
                    'infra_slug',
                    __('panel.validation.infra_panel_only_old'),
                );
            }

            if (! $this->boolean('create_database')) {
                return;
            }

            $engine = $this->input('engine');

            if ($engine === DatabaseEngine::Mysql->value && ! $infrastructure->canProvisionMysql()) {
                $validator->errors()->add(
                    'create_database',
                    __('panel.validation.mariadb_unavailable', ['slug' => $infrastructure->slug]),
                );
            }

            if ($engine === DatabaseEngine::Postgres->value && ! $infrastructure->canProvisionPostgres()) {
                $validator->errors()->add(
                    'create_database',
                    __('panel.validation.postgres_unavailable', ['slug' => $infrastructure->slug]),
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fqdn' => is_string($this->fqdn) ? strtolower($this->fqdn) : $this->fqdn,
            'create_database' => $this->boolean('create_database'),
            'stack' => is_string($this->stack) ? $this->stack : DomainStack::None->value,
        ]);
    }
}
