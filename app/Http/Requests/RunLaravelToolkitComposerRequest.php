<?php

// RunLaravelToolkitComposerRequest.php — RunLaravelToolkitComposerRequest module.
//
// exports: RunLaravelToolkitComposerRequest | RunLaravelToolkitComposerRequest::authorize(): bool | RunLaravelToolkitComposerRequest::rules(): array
// used_by: app/Http/Controllers/LaravelToolkitController.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use Illuminate\Foundation\Http\FormRequest;

class RunLaravelToolkitComposerRequest extends FormRequest
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
            'command' => ['required', 'string', 'max:255'],
        ];
    }
}
