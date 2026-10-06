<?php

namespace App\Models;

use Database\Factories\PartnerLogoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Logo "Mitra resmi" di beranda aplikasi (revisi 6 Oktober 2026).
 *
 * @property string $id
 * @property string $name
 * @property string $logo_path
 * @property string|null $link_url
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PartnerLogo extends Model
{
    /** @use HasFactory<PartnerLogoFactory> */
    use HasFactory, HasUuids, LogsActivity;

    public const LOGO_DIRECTORY = 'partner-logos';

    protected $fillable = [
        'name',
        'logo_path',
        'link_url',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('partner_logo')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function logoUrl(): string
    {
        return Storage::disk('public')->url($this->logo_path);
    }
}
