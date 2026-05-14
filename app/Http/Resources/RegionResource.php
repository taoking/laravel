<?php

namespace App\Http\Resources;

use App\Domains\Metrics\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Region
 */
class RegionResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'code' => $this->code,
            'level' => $this->level,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
