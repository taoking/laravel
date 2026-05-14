<?php

namespace App\Domains\Metrics\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'code',
    'sort_order',
    'is_active',
])]
class Frequency extends Model
{
    public function values(): HasMany
    {
        return $this->hasMany(MetricValue::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
