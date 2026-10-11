<?php

// ImportDomainDatabaseDumpRequest.php — Validates SQL dump upload into a domain database.
//
// exports: ImportDomainDatabaseDumpRequest | authorize(): bool | rules(): array | dumpFile(): \Illuminate\Http\UploadedFile | allowOverwriteProduction(): bool
// used_by: app/Http/Controllers/DomainController.php
// rules:   Mutate hosting only. Accept .sql / .sql.gz; never log dump contents.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_db_import | SQL dump import FormRequest
// message:

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

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
                'file',
                'max:102400',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        $fail(__('panel.domains.show.import_dump_invalid'));

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
