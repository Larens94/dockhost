<?php

// UpdateDomainPhpSettingsRequest.php — Validates per-domain PHP tuning from the panel.
//
// exports: UpdateDomainPhpSettingsRequest | authorize(): bool | rules(): array
// used_by: app/Http/Controllers/DomainController.php
// rules:   readonly members MUST fail authorize; stack must create a Dokploy application.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_php | FormRequest for PHP settings save.

namespace App\Http\Requests;

use App\Enums\DomainStack;
use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use App\Support\DomainPhpSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDomainPhpSettingsRequest extends FormRequest
{
    use AuthorizesDomainHosting;

    public function authorize(): bool
    {
        if (! $this->userCanMutateDomainHosting()) {
            return false;
        }

        $stack = $this->domainFromRoute()->stack ?? DomainStack::None;

        return $stack->createsApplication();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return DomainPhpSettings::validationRules();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $post = (string) $this->input('post_max_size', '');
            $upload = (string) $this->input('upload_max_filesize', '');

            if ($post !== '' && $upload !== '' && self::memoryBytes($post) < self::memoryBytes($upload)) {
                $validator->errors()->add(
                    'post_max_size',
                    'post_max_size deve essere almeno uguale a upload_max_filesize.',
                );
            }
        });
    }

    private static function memoryBytes(string $value): int
    {
        if (preg_match('/^(\d+)([KMG])?$/i', trim($value), $matches) !== 1) {
            return 0;
        }

        $number = (int) $matches[1];
        $unit = strtoupper($matches[2] ?? '');

        return match ($unit) {
            'G' => $number * 1024 * 1024 * 1024,
            'M' => $number * 1024 * 1024,
            'K' => $number * 1024,
            default => $number,
        };
    }
}
