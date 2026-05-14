<?php

namespace App\Domains\Metrics\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'metric_category_id',
    'created_by',
    'name',
    'code',
    'unit',
    'status',
    'description',
    'sort_order',
])]
class Metric extends Model
{
    use SoftDeletes;

    public function category(): BelongsTo
    {
        return $this->belongsTo(MetricCategory::class, 'metric_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function values(): HasMany
    {
        return $this->hasMany(MetricValue::class);
    }

    public function latestValue(): HasOne
    {
        return $this->hasOne(MetricValue::class)->latestOfMany('period_date');
    }
}
