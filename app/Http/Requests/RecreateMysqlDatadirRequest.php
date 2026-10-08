<?php


// RecreateMysqlDatadirRequest.php — RecreateMysqlDatadirRequest module.
//
// exports: RecreateMysqlDatadirRequest | RecreateMysqlDatadirRequest::authorize(): bool | RecreateMysqlDatadirRequest::rules(): array | RecreateMysqlDatadirRequest::messages(): array
// used_by: app/Http/Controllers/InfrastructureController.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Http\Requests;

use App\Models\Infrastructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecreateMysqlDatadirRequest extends FormRequest
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
            'slug.required' => 'Digita lo slug per confermare la ricreazione del datadir MariaDB.',
            'slug.in' => 'Lo slug non corrisponde.',
        ];
    }
}
