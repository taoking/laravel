<?php

namespace App\Modules\Import\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ImportTask extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'status',
        'total_rows',
        'success_rows',
        'failed_rows',
        'progress',
        'error_message',
        'created_by',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ImportTaskLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ImportTaskLog::class);
    }

    /**
     * @return HasOne<UploadedTable, $this>
     */
    public function uploadedTable(): HasOne
    {
        return $this->hasOne(UploadedTable::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
