<?php

namespace App\Modules\DataSource\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataSource extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'host',
        'port',
        'database_name',
        'username',
        'password_encrypted',
        'charset',
        'timezone',
        'options_json',
        'status',
        'last_tested_at',
        'last_test_result',
        'created_by',
        'updated_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password_encrypted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options_json' => 'array',
            'last_tested_at' => 'datetime',
            'last_test_result' => 'array',
        ];
    }

    /**
     * @return HasMany<DataSourceTable, $this>
     */
    public function tables(): HasMany
    {
        return $this->hasMany(DataSourceTable::class);
    }

    /**
     * @return HasMany<DataSourceField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(DataSourceField::class);
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
