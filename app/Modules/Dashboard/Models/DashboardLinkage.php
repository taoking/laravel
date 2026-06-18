<?php

namespace App\Modules\Dashboard\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardLinkage extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dashboard_id',
        'source_widget_id',
        'target_widget_id',
        'source_field',
        'target_field',
        'config_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Dashboard, $this>
     */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    /**
     * @return BelongsTo<DashboardWidget, $this>
     */
    public function sourceWidget(): BelongsTo
    {
        return $this->belongsTo(DashboardWidget::class, 'source_widget_id');
    }

    /**
     * @return BelongsTo<DashboardWidget, $this>
     */
    public function targetWidget(): BelongsTo
    {
        return $this->belongsTo(DashboardWidget::class, 'target_widget_id');
    }
}
