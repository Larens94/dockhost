<?php


// AttachPhpmyadminDomainRequest.php — AttachPhpmyadminDomainRequest module.
//
// exports: AttachPhpmyadminDomainRequest | AttachPhpmyadminDomainRequest::authorize(): bool | AttachPhpmyadminDomainRequest::rules(): array | AttachPhpmyadminDomainRequest::withValidator(Validator $validator): void
// used_by: app/Http/Controllers/InfrastructureController.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Http\Requests;

use App\Models\Infrastructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttachPhpmyadminDomainRequest extends FormRequest
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
                    'phpmyadmin',
                    'Solo gli stack creati da DokHosts possono ricevere il dominio phpMyAdmin.',
                );
            }

            if (! $infrastructure->hasService('phpmyadmin')) {
                $validator->errors()->add(
                    'phpmyadmin',
                    'phpMyAdmin non è attivo su questo stack. Attivalo e aggiorna lo stack prima.',
                );
            }
        });
    }
}
