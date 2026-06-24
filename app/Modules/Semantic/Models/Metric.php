<?php

namespace App\Modules\Semantic\Models;

use App\Models\User;
use App\Modules\Dataset\Models\Dataset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Metric extends Model
{
    use HasFactory;

    public const TYPES = ['base', 'derived', 'compound'];

    public const AGGREGATES = ['sum', 'avg', 'count', 'countDistinct', 'min', 'max', 'expression'];

    public const STATUSES = ['draft', 'active', 'deprecated', 'archived'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'dataset_id',
        'name',
        'code',
        'description',
        'metric_type',
        'aggregate_function',
        'source_field',
        'formula',
        'unit',
        'precision',
        'format_type',
        'status',
        'version',
        'owner_id',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precision' => 'integer',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MetricCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MetricCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<Dataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /**
     * @return HasMany<MetricVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(MetricVersion::class)->orderByDesc('version');
    }

    /**
     * @return HasMany<MetricDependency, $this>
     */
    public function dependencies(): HasMany
    {
        return $this->hasMany(MetricDependency::class);
    }

    /**
     * @return HasMany<MetricUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(MetricUsage::class);
    }

    /**
     * @return HasMany<MetricDependency, $this>
     */
    public function dependents(): HasMany
    {
        return $this->hasMany(MetricDependency::class, 'depends_on_metric_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
