<?php

use App\Modules\Acceleration\Services\AccelerationBenefitReportService;
use App\Modules\Acceleration\Services\AccelerationRecommendationService;
use App\Modules\Acceleration\Services\AccelerationRefreshRunner;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bi:acceleration:recommend {--days=7} {--dataset=} {--dry-run}', function () {
    $service = app(AccelerationRecommendationService::class);
    $dataset = $this->option('dataset');
    $result = $service->generateRecommendations(
        days: (int) $this->option('days'),
        datasetId: is_numeric($dataset) ? (int) $dataset : null,
        dryRun: (bool) $this->option('dry-run'),
    );

    $this->info('Acceleration recommendation analysis complete.');
    $this->line('Scanned logs: '.$result['query_count']);
    $this->line('Slow queries: '.$result['slow_query_count']);
    $this->line('Fallback count: '.$result['fallback_count']);
    $this->line('Generated recommendations: '.$result['generated_count']);
    $this->line('Dry run: '.($result['dry_run'] ? 'yes' : 'no'));

    return 0;
})->purpose('Analyze query logs and generate acceleration recommendations');

Artisan::command('bi:acceleration:refresh-due {--dry-run}', function () {
    $runner = app(AccelerationRefreshRunner::class);
    $result = $runner->runDue((bool) $this->option('dry-run'));

    $this->info('Acceleration refresh scan complete.');
    $this->line('Due schedules: '.$result['due_count']);
    $this->line('Dispatched tasks: '.$result['dispatched_count']);
    $this->line('Failed schedules: '.$result['failed_count']);
    $this->line('Dry run: '.($result['dry_run'] ? 'yes' : 'no'));

    return 0;
})->purpose('Dispatch due acceleration refresh schedules');

Artisan::command('bi:acceleration:benefit-report {--date=} {--days=1}', function () {
    $service = app(AccelerationBenefitReportService::class);
    $date = $this->option('date');
    $result = $service->generateSnapshot(
        date: is_string($date) && $date !== '' ? $date : null,
        days: (int) $this->option('days'),
    );

    $this->info('Acceleration benefit report generated.');
    $this->line('Report date: '.$result['report_date']);
    $this->line('Query count: '.$result['query_count']);
    $this->line('Detail hits: '.$result['detail_hit_count']);
    $this->line('Aggregate hits: '.$result['aggregate_hit_count']);
    $this->line('Cache hits: '.$result['cache_hit_count']);
    $this->line('Fallback count: '.$result['fallback_count']);
    $this->line('Estimated saved ms: '.($result['estimated_saved_ms'] ?? 'n/a'));
    $this->line('Snapshots written: '.$result['snapshot_count']);

    return 0;
})->purpose('Generate acceleration benefit report snapshots');

$refreshInterval = (int) config('bi_acceleration.refresh.scheduler_interval_minutes', 5);
$refreshSchedule = Schedule::command('bi:acceleration:refresh-due')->withoutOverlapping();

match ($refreshInterval) {
    1 => $refreshSchedule->everyMinute(),
    10 => $refreshSchedule->everyTenMinutes(),
    15 => $refreshSchedule->everyFifteenMinutes(),
    30 => $refreshSchedule->everyThirtyMinutes(),
    default => $refreshSchedule->everyFiveMinutes(),
};
