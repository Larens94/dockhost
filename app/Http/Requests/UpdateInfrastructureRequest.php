<?php


// UpdateInfrastructureRequest.php — UpdateInfrastructureRequest module.
//
// exports: UpdateInfrastructureRequest | UpdateInfrastructureRequest::authorize(): bool | UpdateInfrastructureRequest::rules(): array
// used_by: app/Http/Controllers/InfrastructureController.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Http\Requests;

use App\Services\Infra\ComposeTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInfrastructureRequest extends FormRequest
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
        $allowed = app(ComposeTemplate::class)->allowedServiceKeys();

        return [
            'enabled_services' => ['nullable', 'array'],
            'enabled_services.*' => ['string', Rule::in($allowed)],
        ];
    }
}
