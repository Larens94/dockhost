<?php


// StoreInfrastructureDatabaseUserRequest.php — StoreInfrastructureDatabaseUserRequest module.
//
// exports: StoreInfrastructureDatabaseUserRequest | StoreInfrastructureDatabaseUserRequest::authorize(): bool | StoreInfrastructureDatabaseUserRequest::rules(): array | StoreInfrastructureDatabaseUserRequest::messages(): array | StoreInfrastructureDatabaseUserRequest::withValidator(Validator $validator): void | StoreInfrastructureDatabaseUserRequest::sourceAccount(): DatabaseAccount
// used_by: app/Http/Controllers/InfrastructureController.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Http\Requests;

use App\Enums\DatabasePrivilege;
use App\Models\DatabaseAccount;
use App\Models\Infrastructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInfrastructureDatabaseUserRequest extends FormRequest
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
            'database_account_id' => ['required', 'integer', Rule::exists('database_accounts', 'id')],
            'privilege' => ['required', Rule::enum(DatabasePrivilege::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'database_account_id.required' => 'Scegli il database su cui concedere i privilegi.',
            'privilege.required' => 'Scegli i privilegi: completi (ALL) o sola lettura (SELECT).',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $infrastructure = $this->route('infrastructure');
            $account = DatabaseAccount::query()->find($this->input('database_account_id'));

            if (! $infrastructure instanceof Infrastructure || ! $account instanceof DatabaseAccount) {
                return;
            }

            if ((int) $account->infrastructure_id !== (int) $infrastructure->id && $account->infra_slug !== $infrastructure->slug) {
                $validator->errors()->add(
                    'database_account_id',
                    'Il database non appartiene a questa infrastruttura.',
                );
            }
        });
    }

    public function sourceAccount(): DatabaseAccount
    {
        return DatabaseAccount::query()->findOrFail($this->validated('database_account_id'));
    }
}
