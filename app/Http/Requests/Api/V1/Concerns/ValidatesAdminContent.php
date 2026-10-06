<?php

namespace App\Http\Requests\Api\V1\Concerns;

/**
 * Pesan validasi berbahasa Indonesia dan normalisasi boolean untuk form
 * konten yang dikirim Admin Panel aplikasi.
 *
 * Form unggah gambar dikirim sebagai multipart, sehingga boolean tiba
 * sebagai teks "true"/"false" yang tidak diterima aturan `boolean` Laravel.
 */
trait ValidatesAdminContent
{
    /** @param  list<string>  $fields */
    protected function normalizeBooleans(array $fields): void
    {
        $normalized = [];
        foreach ($fields as $field) {
            if (! $this->has($field)) {
                continue;
            }
            $value = $this->input($field);
            if (is_string($value)) {
                $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($parsed !== null) {
                    $normalized[$field] = $parsed;
                }
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_with' => ':attribute wajib diisi bila :values diisi.',
            'string' => ':attribute harus berupa teks.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'boolean' => ':attribute harus bernilai ya atau tidak.',
            'date' => ':attribute bukan tanggal yang valid.',
            'array' => ':attribute harus berupa daftar.',
            'image' => ':attribute harus berupa gambar.',
            'mimes' => ':attribute harus berformat :values.',
            'url' => ':attribute harus berupa tautan http:// atau https:// yang valid.',
            'min' => [
                'numeric' => ':attribute minimal :min.',
                'string' => ':attribute minimal :min karakter.',
                'array' => ':attribute minimal berisi :min item.',
                'file' => ':attribute minimal :min KB.',
            ],
            'max' => [
                'numeric' => ':attribute maksimal :max.',
                'string' => ':attribute maksimal :max karakter.',
                'array' => ':attribute maksimal berisi :max item.',
                'file' => ':attribute maksimal :max KB.',
            ],
            'uploaded' => ':attribute gagal diunggah. Pastikan ukurannya tidak melebihi batas.',
        ];
    }
}
