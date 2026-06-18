<?php

namespace App\Modules\Dashboard\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dashboard extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'layout_json',
        'global_filters_json',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'layout_json' => 'array',
            'global_filters_json' => 'array',
        ];
    }

    /**
     * @return HasMany<DashboardWidget, $this>
     */
    public function widgets(): HasMany
    {
        return $this->hasMany(DashboardWidget::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<DashboardFilter, $this>
     */
    public function filters(): HasMany
    {
        return $this->hasMany(DashboardFilter::class);
    }

    /**
     * @return HasMany<DashboardLinkage, $this>
     */
    public function linkages(): HasMany
    {
        return $this->hasMany(DashboardLinkage::class);
    }

    /**
     * @return HasMany<DashboardShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(DashboardShare::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
