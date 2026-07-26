<?php

namespace App\Services;

use App\Models\Asset;
use App\Support\Enums\AssetStatus;
use App\Support\Enums\StorageType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AssetUploadService
{
    public function __construct(
        private readonly AssetMetadataExtractor $metadataExtractor,
    ) {}

    /**
     * Upload file langsung ke GCS lalu simpan record asset.
     * Atomic: bila upload GCS gagal, tidak ada record DB yang dibuat;
     * bila penyimpanan DB gagal setelah upload, file GCS yang sudah terupload dihapus kembali.
     */
    public function upload(
        UploadedFile $file,
        string $type,
        ?int $userId = null,
        ?Carbon $retainUntil = null,
        bool $isProtected = false,
    ): Asset {
        // UUID dipakai sekaligus sebagai PK asset dan nama file di GCS (tidak bisa ditebak).
        $uuid = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';

        // Path scalable: {env}/{type}/{year}/{month}/{uuid}.{ext}
        $folder = sprintf(
            '%s/%s/%s/%s',
            config('app.env', 'production'),
            Str::slug($type),
            now()->format('Y'),
            now()->format('m'),
        );
        $filename = $uuid.'.'.$extension;
        $path = $folder.'/'.$filename;

        // Ekstrak metadata SEBELUM upload — setelah store(), file sementara sudah dipindah.
        $metadata = $this->metadataExtractor->extract($file);

        $diskName = config('filesystems.asset_upload_disk', 'gcs');
        $storageType = match ($diskName) {
            'gcs' => StorageType::Gcs,
            'local' => StorageType::PrivateLocal,
            'public' => StorageType::Local,
            default => throw new RuntimeException('Unsupported asset upload disk.'),
        };
        $disk = Storage::disk($diskName);

        // Disk 'gcs' dikonfigurasi 'throw' => true, jadi kegagalan upload melempar exception
        // (tertangkap handler global) dan kita tidak pernah sampai membuat record DB.
        $stored = $disk->putFileAs($folder, $file, $filename);
        if ($stored === false) {
            throw new RuntimeException('Failed to upload file to Google Cloud Storage.');
        }

        try {
            $asset = new Asset([
                'user_id' => $userId,
                'storage_type' => $storageType,
                'path' => $path,
                'url' => $isProtected ? null : $disk->url($path),
                'original_filename' => $file->getClientOriginalName(),
                'extension' => $extension,
                'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'checksum' => $this->checksum($file),
                'category' => Str::slug($type),
                'metadata' => $metadata,
                'retain_until' => $retainUntil,
                'is_protected' => $isProtected,
            ]);

            // id & status di-set langsung (bukan via mass-assignment) agar id deterministik
            // sama dengan nama file di storage, dan status tidak pernah bisa di-inject klien.
            $asset->id = $uuid;
            $asset->status = AssetStatus::Active;
            $asset->save();

            return $asset;
        } catch (Throwable $e) {
            // Kompensasi: bersihkan file yatim di GCS agar tidak ada orphan tanpa record.
            $disk->delete($path);

            throw $e;
        }
    }

    private function checksum(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return null;
        }

        $hash = @hash_file('sha256', $path);

        return $hash === false ? null : $hash;
    }
}
