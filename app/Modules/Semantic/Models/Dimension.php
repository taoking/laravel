<?php

namespace App\Modules\Semantic\Models;

use App\Models\User;
use App\Modules\Dataset\Models\Dataset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dimension extends Model
{
    use HasFactory;

    public const TYPES = ['string', 'number', 'date', 'datetime', 'region', 'organization', 'user', 'enum'];

    public const STATUSES = ['active', 'disabled'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dataset_id',
        'name',
        'code',
        'field_name',
        'dimension_type',
        'time_grain_options_json',
        'description',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_grain_options_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Dataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
