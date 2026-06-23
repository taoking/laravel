<?php

namespace App\Modules\Acceleration\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccelerationTask extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'acceleration_profile_id',
        'task_type',
        'status',
        'started_at',
        'finished_at',
        'source_row_count',
        'target_row_count',
        'duration_ms',
        'error_message',
        'logs_json',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'source_row_count' => 'integer',
            'target_row_count' => 'integer',
            'duration_ms' => 'integer',
            'logs_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<AccelerationProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(AccelerationProfile::class, 'acceleration_profile_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
