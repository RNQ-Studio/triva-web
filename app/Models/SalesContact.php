<?php

namespace App\Models;

use App\Support\Enums\SalesContactRole;
use Database\Factories\SalesContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $name
 * @property SalesContactRole $role
 * @property string $whatsapp_number
 * @property string|null $photo_path
 * @property int $sort_order
 * @property bool $is_active
 */
class SalesContact extends Model
{
    /** @use HasFactory<SalesContactFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'name',
        'role',
        'whatsapp_number',
        'photo_path',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'role' => SalesContactRole::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('sales_contact')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @param Builder<SalesContact> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Nomor dalam format internasional tanpa tanda plus (62812...), siap
     * dipakai pada tautan wa.me. Menerima 08..., +62..., atau 62....
     */
    public static function normalizeWhatsapp(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }
        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }

    public function photoUrl(): ?string
    {
        if (blank($this->photo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->photo_path);
    }
}
