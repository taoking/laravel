<?php

namespace App\Modules\Import\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportTaskLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'import_task_id' => $this->import_task_id,
            'row_number' => $this->row_number,
            'status' => $this->status,
            'message' => $this->message,
            'raw_data_json' => $this->raw_data_json,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
