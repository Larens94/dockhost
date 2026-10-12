<?php

// ImportDomainDatabaseDumpRequest.php — Validates SQL dump upload into a domain database.
//
// exports: ImportDomainDatabaseDumpRequest | authorize(): bool | rules(): array | dumpFile(): \Illuminate\Http\UploadedFile | allowOverwriteProduction(): bool
// used_by: app/Http/Controllers/DomainController.php
// rules:   Mutate hosting only. Accept .sql / .sql.gz; never log dump contents. Enforce panel upload limits with clear errors.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_db_import | SQL dump import FormRequest
//          composer-2.5-fast | cursor | 2026-10-12 | s_db_import_limits | Upload validation + size cap
// message:

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use App\Support\PanelDatabaseImportLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class ImportDomainDatabaseDumpRequest extends FormRequest
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
            'dump' => [
                'required',
                'max:'.PanelDatabaseImportLimits::maxKilobytes(),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        $fail(__('panel.domains.show.import_dump_invalid'));

                        return;
                    }

                    if (! $value->isValid()) {
                        $fail(PanelDatabaseImportLimits::uploadErrorMessage($value));

                        return;
                    }

                    $name = strtolower($value->getClientOriginalName());

                    if (! str_ends_with($name, '.sql') && ! str_ends_with($name, '.sql.gz')) {
                        $fail(__('panel.domains.show.import_dump_invalid'));
                    }
                },
            ],
            'confirm_overwrite' => ['accepted'],
            'allow_overwrite_production' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('dump');

            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                return;
            }

            $maxBytes = PanelDatabaseImportLimits::maxBytes();
            $size = $file->getSize();

            if ($size !== false && $size > $maxBytes) {
                $validator->errors()->add('dump', __('panel.domains.show.import_dump_too_large', [
                    'max' => PanelDatabaseImportLimits::inertiaPayload()['max_label'],
                ]));
            }
        });
    }

    public function dumpFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('dump');

        return $file;
    }

    public function allowOverwriteProduction(): bool
    {
        return $this->boolean('allow_overwrite_production');
    }
}
