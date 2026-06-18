<?php

namespace App\Modules\Query\Models;

use App\Models\User;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dashboard\Models\Dashboard;
use App\Modules\Dataset\Models\Dataset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryLog extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'dataset_id',
        'chart_id',
        'dashboard_id',
        'query_hash',
        'sql',
        'bindings_json',
        'elapsed_ms',
        'row_count',
        'cached',
        'is_slow',
        'status',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bindings_json' => 'array',
            'cached' => 'boolean',
            'is_slow' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Dataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /**
     * @return BelongsTo<Chart, $this>
     */
    public function chart(): BelongsTo
    {
        return $this->belongsTo(Chart::class);
    }

    /**
     * @return BelongsTo<Dashboard, $this>
     */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }
}
