<?php

namespace App\Modules\Dashboard\Resources;

use App\Modules\Chart\Resources\ChartResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardWidgetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dashboard_id' => $this->dashboard_id,
            'chart_id' => $this->chart_id,
            'chart' => $this->whenLoaded(
                'chart',
                fn () => (new ChartResource($this->chart))->resolve($request),
            ),
            'widget_type' => $this->widget_type,
            'x' => $this->x,
            'y' => $this->y,
            'w' => $this->w,
            'h' => $this->h,
            'config_json' => $this->config_json,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
