<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeInRankOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw('sort_order IS NULL')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function staffProfiles()
    {
        return $this->hasMany(StaffProfile::class);
    }

    public function jobDetail(): HasOne
    {
        return $this->hasOne(PositionJobDetail::class);
    }
}
