<?php

namespace App\Domains\Access\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'parent_id',
    'permission_id',
    'title',
    'route_name',
    'path',
    'icon',
    'sort_order',
    'is_visible',
])]
class Menu extends Model
{
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
        ];
    }
}
