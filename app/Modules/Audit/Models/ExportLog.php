<?php

namespace App\Modules\Audit\Models;

use App\Models\User;
use App\Modules\Export\Models\ExportTask;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'export_task_id',
        'source_type',
        'source_id',
        'file_path',
        'status',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
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
     * @return BelongsTo<ExportTask, $this>
     */
    public function exportTask(): BelongsTo
    {
        return $this->belongsTo(ExportTask::class);
    }
}
