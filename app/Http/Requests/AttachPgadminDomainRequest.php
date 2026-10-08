<?php

// AttachPgadminDomainRequest.php — AttachPgadminDomainRequest module.
//
// exports: AttachPgadminDomainRequest | AttachPgadminDomainRequest::authorize(): bool | AttachPgadminDomainRequest::rules(): array | AttachPgadminDomainRequest::withValidator(Validator $validator): void
// used_by: app/Http/Controllers/InfrastructureController.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace App\Http\Requests;

use App\Models\Infrastructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttachPgadminDomainRequest extends FormRequest
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
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $infrastructure = $this->route('infrastructure');

            if (! $infrastructure instanceof Infrastructure) {
                return;
            }

            if (! $infrastructure->isPanelManaged()) {
                $validator->errors()->add(
                    'pgadmin',
                    __('panel.validation.pgadmin_panel_only'),
                );
            }

            if (! $infrastructure->hasService('pgadmin')) {
                $validator->errors()->add(
                    'pgadmin',
                    __('panel.validation.pgadmin_off'),
                );
            }
        });
    }
}
