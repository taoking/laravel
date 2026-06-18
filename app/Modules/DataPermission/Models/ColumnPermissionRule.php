<?php

namespace App\Modules\DataPermission\Models;

use App\Modules\Dataset\Models\Dataset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColumnPermissionRule extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'dataset_id',
        'subject_type',
        'subject_id',
        'field_name',
        'permission_type',
    ];

    /**
     * @return BelongsTo<Dataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}
