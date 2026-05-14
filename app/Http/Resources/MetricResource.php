<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetricResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'unit' => $this->unit,
            'status' => $this->status,
            'description' => $this->description,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'code' => $this->category->code,
            ]),
            'latest_value' => $this->whenLoaded('latestValue', fn () => $this->latestValue ? [
                'id' => $this->latestValue->id,
                'value' => $this->latestValue->value,
                'period_date' => $this->latestValue->period_date?->toDateString(),
                'period_label' => $this->latestValue->period_label,
                'region' => $this->latestValue->relationLoaded('region') && $this->latestValue->region ? [
                    'id' => $this->latestValue->region->id,
                    'name' => $this->latestValue->region->name,
                    'code' => $this->latestValue->region->code,
                ] : null,
                'frequency' => $this->latestValue->relationLoaded('frequency') && $this->latestValue->frequency ? [
                    'id' => $this->latestValue->frequency->id,
                    'name' => $this->latestValue->frequency->name,
                    'code' => $this->latestValue->frequency->code,
                ] : null,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
