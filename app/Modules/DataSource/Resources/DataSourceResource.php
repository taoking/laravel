<?php

namespace App\Modules\DataSource\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataSourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'type' => $this->type,
            'host' => $this->host,
            'port' => $this->port,
            'database_name' => $this->database_name,
            'username' => $this->username,
            'charset' => $this->charset,
            'timezone' => $this->timezone,
            'options_json' => $this->options_json,
            'status' => $this->status,
            'last_tested_at' => $this->last_tested_at?->toISOString(),
            'last_test_result' => $this->last_test_result,
            'tables_count' => $this->whenCounted('tables'),
            'fields_count' => $this->whenCounted('fields'),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
