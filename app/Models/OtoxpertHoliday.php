<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $workshop_id
 * @property Carbon $holiday_date
 * @property string $name
 * @property bool $is_closed
 * @property array<int, string>|null $time_windows
 */
class OtoxpertHoliday extends Model
{
    protected $fillable = [
        'workshop_id',
        'holiday_date',
        'name',
        'is_closed',
        'time_windows',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_closed' => 'boolean',
            'time_windows' => 'array',
        ];
    }

    /** @return BelongsTo<OtoxpertWorkshop, $this> */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(OtoxpertWorkshop::class, 'workshop_id');
    }
}
