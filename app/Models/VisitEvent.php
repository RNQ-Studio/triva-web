<?php

namespace App\Models;

use App\Support\Enums\VisitSource;
use Database\Factories\VisitEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property VisitSource $source
 * @property string $visit_key
 * @property Carbon $occurred_at
 * @property string|null $app_version
 * @property string|null $app_build
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VisitEvent extends Model
{
    /** @use HasFactory<VisitEventFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'source',
        'visit_key',
        'occurred_at',
        'app_version',
        'app_build',
    ];

    protected function casts(): array
    {
        return [
            'source' => VisitSource::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
