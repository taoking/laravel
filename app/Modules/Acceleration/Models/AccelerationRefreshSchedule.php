<?php

namespace App\Modules\Acceleration\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccelerationRefreshSchedule extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'target_type',
        'target_id',
        'refresh_type',
        'cron_expression',
        'enabled',
        'last_run_at',
        'next_run_at',
        'last_task_id',
        'last_status',
        'last_error_message',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AccelerationTask, $this>
     */
    public function lastTask(): BelongsTo
    {
        return $this->belongsTo(AccelerationTask::class, 'last_task_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
