<?php

namespace App\Modules\DataPermission\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataPermissionRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'dataset_id' => $this->dataset_id,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'field_name' => $this->field_name,
            'operator' => $this->operator,
            'value_type' => $this->value_type,
            'value_json' => $this->ruleValue(),
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
