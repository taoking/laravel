<?php

namespace App\Domains\Dashboard\Observers;

use App\Domains\Dashboard\Services\DashboardSummaryService;
use Illuminate\Database\Eloquent\Model;

class RefreshDashboardSummaryObserver
{
    public function __construct(private readonly DashboardSummaryService $summary) {}

    public function saved(Model $model): void
    {
        $this->summary->forget();
    }

    public function deleted(Model $model): void
    {
        $this->summary->forget();
    }

    public function restored(Model $model): void
    {
        $this->summary->forget();
    }

    public function forceDeleted(Model $model): void
    {
        $this->summary->forget();
    }
}
