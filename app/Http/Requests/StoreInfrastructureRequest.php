<?php


// StoreInfrastructureRequest.php — StoreInfrastructureRequest module.
//
// exports: StoreInfrastructureRequest | StoreInfrastructureRequest::authorize(): bool | StoreInfrastructureRequest::rules(): array
// used_by: app/Http/Controllers/InfrastructureController.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Http\Requests;

use App\Services\Infra\ComposeTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInfrastructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => filled($this->input('name')) ? $this->input('name') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowed = app(ComposeTemplate::class)->allowedServiceKeys();

        return [
            'slug' => ['required', 'string', 'max:32', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:infrastructures,slug'],
            'name' => ['nullable', 'string', 'max:255'],
            'template' => ['nullable', 'string', Rule::in(['base'])],
            'enabled_services' => ['nullable', 'array'],
            'enabled_services.*' => ['string', Rule::in($allowed)],
        ];
    }
}
