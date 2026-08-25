<?php

namespace App\Models;

use App\Support\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property DevicePlatform $platform
 * @property int $version_code
 * @property string $version_name
 * @property string $apk_url
 * @property string $apk_sha256
 * @property int $apk_size_bytes
 * @property string $storage_path
 * @property bool $is_active
 * @property string|null $release_notes
 */
class AppRelease extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform',
        'version_code',
        'version_name',
        'apk_url',
        'apk_sha256',
        'apk_size_bytes',
        'storage_path',
        'is_active',
        'release_notes',
    ];

    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'version_code' => 'integer',
            'apk_size_bytes' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
