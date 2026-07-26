<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerConsent extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'granted',
        'policy_version',
        'source',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'captured_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
