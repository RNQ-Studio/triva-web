<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Menyimpan gambar konten beranda (popup informasi, logo mitra) di disk
 * `public` bersama barisnya, tanpa meninggalkan berkas yatim: berkas baru
 * dihapus bila baris gagal disimpan, dan berkas lama dihapus setelah
 * penggantinya tersimpan.
 */
class PublicImageService
{
    private const DISK = 'public';

    /**
     * @template TModel of Model
     *
     * @param  TModel  $model  Instans baru yang belum tersimpan.
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(
        Model $model,
        array $attributes,
        UploadedFile $image,
        string $column,
        string $directory,
    ): Model {
        $path = $this->store($image, $directory);

        try {
            $model->fill($attributes);
            $model->setAttribute($column, $path);
            $model->save();
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($path);

            throw $exception;
        }

        return $model->refresh();
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(
        Model $model,
        array $attributes,
        ?UploadedFile $image,
        string $column,
        string $directory,
    ): Model {
        $previousPath = $model->getAttribute($column);
        $newPath = $image === null ? null : $this->store($image, $directory);

        try {
            $model->fill($attributes);
            if ($newPath !== null) {
                $model->setAttribute($column, $newPath);
            }
            $model->save();
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk(self::DISK)->delete($newPath);
            }

            throw $exception;
        }

        if ($newPath !== null && is_string($previousPath) && $previousPath !== $newPath) {
            Storage::disk(self::DISK)->delete($previousPath);
        }

        return $model->refresh();
    }

    public function delete(Model $model, string $column): void
    {
        $path = $model->getAttribute($column);
        $model->delete();

        if (is_string($path) && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    private function store(UploadedFile $image, string $directory): string
    {
        $path = $image->store($directory, self::DISK);
        if ($path === false) {
            throw new RuntimeException('Gambar gagal disimpan ke penyimpanan publik.');
        }

        return $path;
    }
}
