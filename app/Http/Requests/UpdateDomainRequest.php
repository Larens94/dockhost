<?php


// UpdateDomainRequest.php — UpdateDomainRequest module.
//
// exports: UpdateDomainRequest | UpdateDomainRequest::authorize(): bool | UpdateDomainRequest::rules(): array
// used_by: app/Http/Controllers/DomainController.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Http\Requests;

use App\Models\Domain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDomainRequest extends FormRequest
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
        $domain = $this->route('domain');
        $domainId = $domain instanceof Domain ? $domain->id : null;

        return [
            'fqdn' => ['required', 'string', 'max:255', Rule::unique('domains', 'fqdn')->ignore($domainId)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fqdn' => is_string($this->fqdn) ? strtolower($this->fqdn) : $this->fqdn,
        ]);
    }
}
