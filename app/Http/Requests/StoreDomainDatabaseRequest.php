<?php

// StoreDomainDatabaseRequest.php — StoreDomainDatabaseRequest module.
//
// exports: StoreDomainDatabaseRequest | StoreDomainDatabaseRequest::authorize(): bool | StoreDomainDatabaseRequest::rules(): array | StoreDomainDatabaseRequest::withValidator(Validator $validator): void
// used_by: app/Http/Controllers/DomainController.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace App\Http\Requests;

use App\Enums\DatabaseEngine;
use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use App\Models\Domain;
use App\Models\Infrastructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDomainDatabaseRequest extends FormRequest
{
    use AuthorizesDomainHosting;

    public function authorize(): bool
    {
        return $this->userCanMutateDomainHosting();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'engine' => ['required', Rule::enum(DatabaseEngine::class)],
            'infra_slug' => ['nullable', 'string', 'max:64', Rule::exists('infrastructures', 'slug')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('infra_slug');

        $this->merge([
            'infra_slug' => is_string($slug) && $slug !== '' ? $slug : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $domain = $this->route('domain');
            $slug = $this->input('infra_slug');

            if (! is_string($slug) || $slug === '') {
                $slug = $domain instanceof Domain ? $domain->infra_slug : null;
            }

            $infrastructure = Infrastructure::query()->where('slug', $slug)->first();

            if (! $infrastructure instanceof Infrastructure || ! $infrastructure->isPanelManaged()) {
                $validator->errors()->add(
                    'infra_slug',
                    'Scegli un’infrastruttura creata da DokHosts.',
                );

                return;
            }

            $engine = $this->input('engine');

            if ($engine === DatabaseEngine::Mysql->value && ! $infrastructure->canProvisionMysql()) {
                $validator->errors()->add(
                    'engine',
                    'MariaDB non è disponibile su '.$infrastructure->slug.'.',
                );
            }

            if ($engine === DatabaseEngine::Postgres->value && ! $infrastructure->canProvisionPostgres()) {
                $validator->errors()->add(
                    'engine',
                    'Postgres non è disponibile su '.$infrastructure->slug.'.',
                );
            }
        });
    }
}
