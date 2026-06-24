<?php

namespace App\Modules\DataPermission\Models;

use App\Models\User;
use App\Modules\Dataset\Models\Dataset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataPermissionRule extends Model
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
        'operator',
        'value_type',
        'value_json',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Dataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    public function ruleValue(?User $user = null): mixed
    {
        if ($this->value_type === 'current_user_id') {
            return $user?->id;
        }

        if ($this->value_type === 'current_user_departments') {
            return $user?->department_id !== null ? [(int) $user->department_id] : [];
        }

        if (is_array($this->value_json) && array_key_exists('value', $this->value_json)) {
            return $this->value_json['value'];
        }

        return $this->value_json;
    }
}
