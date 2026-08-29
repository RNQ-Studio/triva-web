<?php

namespace App\Models;

use App\Support\Enums\VisitSource;
use Database\Factories\MenuUsageEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int|null $user_id
 * @property string $menu_key
 * @property VisitSource $source
 * @property Carbon $occurred_at
 * @property string|null $app_version
 * @property string|null $app_build
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MenuUsageEvent extends Model
{
    /** @use HasFactory<MenuUsageEventFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'menu_key',
        'source',
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
