<?php

// DestroyInfrastructureRequest.php — DestroyInfrastructureRequest module.
//
// exports: DestroyInfrastructureRequest | DestroyInfrastructureRequest::authorize(): bool | DestroyInfrastructureRequest::rules(): array | DestroyInfrastructureRequest::messages(): array | DestroyInfrastructureRequest::withValidator(Validator $validator): void
// used_by: app/Http/Controllers/InfrastructureController.php
// rules:   Agents must NOT invent infra-delete shortcuts — only this FormRequest may authorize destroy.
//          Do not weaken confirmation / authorization checks; never auto-delete stacks from other flows.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
//          composer | cursor | 2026-09-21 | s_20260921_codedna | harden no-delete agent rule

namespace App\Http\Requests;

use App\Models\Infrastructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DestroyInfrastructureRequest extends FormRequest
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
        $infrastructure = $this->route('infrastructure');
        $slug = $infrastructure instanceof Infrastructure ? $infrastructure->slug : '';

        return [
            'slug' => ['required', 'string', Rule::in([$slug])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.required' => __('panel.validation.slug_delete_required'),
            'slug.in' => __('panel.validation.slug_mismatch'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $infrastructure = $this->route('infrastructure');

            if (! $infrastructure instanceof Infrastructure) {
                return;
            }

            if ($infrastructure->domains()->exists()) {
                $validator->errors()->add('slug', __('panel.validation.domains_linked'));
            }
        });
    }
}
