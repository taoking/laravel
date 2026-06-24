<?php

namespace App\Modules\DataPermission\DTO;

use App\Models\User;
use App\Modules\Dataset\Models\Dataset;

class PermissionContext
{
    public function __construct(
        public readonly Dataset $dataset,
        public readonly ?User $user,
        public readonly string $requestSource = 'api',
        public readonly ?int $chartId = null,
        public readonly ?int $dashboardId = null,
        public readonly ?int $metricId = null,
    ) {}
}
