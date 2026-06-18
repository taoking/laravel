<?php

namespace App\Modules\Dashboard\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardFilterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dashboard_id' => $this->dashboard_id,
            'field_name' => $this->field_name,
            'label' => $this->label,
            'filter_type' => $this->filter_type,
            'default_value_json' => $this->default_value_json,
            'config_json' => $this->config_json,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
