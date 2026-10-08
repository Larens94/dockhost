<?php


// StoreInfrastructureSftpUserRequest.php — StoreInfrastructureSftpUserRequest module.
//
// exports: StoreInfrastructureSftpUserRequest | StoreInfrastructureSftpUserRequest::authorize(): bool | StoreInfrastructureSftpUserRequest::rules(): array | StoreInfrastructureSftpUserRequest::messages(): array | StoreInfrastructureSftpUserRequest::withValidator(Validator $validator): void | StoreInfrastructureSftpUserRequest::domain(): Domain
// used_by: app/Http/Controllers/InfrastructureController.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Http\Requests;

use App\Models\Domain;
use App\Models\Infrastructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInfrastructureSftpUserRequest extends FormRequest
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
            'domain_id' => ['required', 'integer', Rule::exists('domains', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'domain_id.required' => 'Scegli il dominio per la home SFTP.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $infrastructure = $this->route('infrastructure');
            $domain = Domain::query()->find($this->input('domain_id'));

            if (! $infrastructure instanceof Infrastructure || ! $domain instanceof Domain) {
                return;
            }

            if ((int) $domain->infrastructure_id !== (int) $infrastructure->id) {
                $validator->errors()->add(
                    'domain_id',
                    'Il dominio non appartiene a questa infrastruttura.',
                );
            }
        });
    }

    public function domain(): Domain
    {
        return Domain::query()->findOrFail($this->validated('domain_id'));
    }
}
