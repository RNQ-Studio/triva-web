<?php

namespace App\Models;

use App\Support\Enums\AppraisalStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $appraisal_id
 * @property AppraisalStatus $status
 * @property string $title
 * @property string|null $description
 * @property bool $user_visible
 * @property int|null $changed_by
 * @property Carbon|null $created_at
 */
class AppraisalStatusHistory extends Model
{
    use HasUuids;

    protected $fillable = [
        'status',
        'title',
        'description',
        'user_visible',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AppraisalStatus::class,
            'user_visible' => 'boolean',
        ];
    }

    /** @return BelongsTo<Appraisal, $this> */
    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
