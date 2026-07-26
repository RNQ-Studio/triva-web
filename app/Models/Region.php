<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $fillable = [
        'parent_id',
        'type',
        'code',
        'name',
        'phone_code',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Region::class, 'parent_id');
    }

    public function childCities(): HasMany
    {
        return $this->children()->where('type', 'city');
    }

    public function scopeCountries(Builder $query): Builder
    {
        return $query->where('type', 'country');
    }

    public function scopeStates(Builder $query): Builder
    {
        return $query->where('type', 'state');
    }

    public function scopeCities(Builder $query): Builder
    {
        return $query->where('type', 'city');
    }

    public function scopeDistricts(Builder $query): Builder
    {
        return $query->where('type', 'district');
    }

    public function scopeVillages(Builder $query): Builder
    {
        return $query->where('type', 'village');
    }
}
