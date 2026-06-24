<?php

namespace App\Modules\Metadata\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetadataAssetTag extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'asset_type',
        'asset_id',
        'tag_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'asset_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MetadataTag, $this>
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(MetadataTag::class, 'tag_id');
    }
}
