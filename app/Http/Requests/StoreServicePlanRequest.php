<?php


// StoreServicePlanRequest.php — StoreServicePlanRequest module.
//
// exports: StoreServicePlanRequest | StoreServicePlanRequest::authorize(): bool | StoreServicePlanRequest::rules(): array
// used_by: app/Http/Controllers/ServicePlanController.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreServicePlanRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:service_plans,slug'],
            'max_domains' => ['nullable', 'integer', 'min:1'],
            'disk_mb' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => blank($this->slug) && filled($this->name)
                ? Str::slug((string) $this->name)
                : $this->slug,
            'max_domains' => $this->filled('max_domains') ? $this->input('max_domains') : null,
            'disk_mb' => $this->filled('disk_mb') ? $this->input('disk_mb') : null,
        ]);
    }
}
