<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExplainMetricQueryCommand extends Command
{
    protected $signature = 'metrics:explain-query
        {--region-code=CN-SH : Region code filter}
        {--frequency-code=monthly : Frequency code filter}
        {--date-from=2026-01-01 : Start date filter}
        {--limit=20 : Limit rows}';

    protected $description = 'Run EXPLAIN for the metric query used in the performance lab.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $sql = <<<'SQL'
SELECT m.id, m.code, m.name
FROM metrics m
WHERE m.status = ?
  AND EXISTS (
    SELECT 1
    FROM metric_values mv
    INNER JOIN regions r ON r.id = mv.region_id
    INNER JOIN frequencies f ON f.id = mv.frequency_id
    WHERE mv.metric_id = m.id
      AND r.code = ?
      AND f.code = ?
      AND mv.period_date >= ?
  )
ORDER BY m.id DESC
LIMIT ?
SQL;

        $bindings = [
            'active',
            (string) $this->option('region-code'),
            (string) $this->option('frequency-code'),
            (string) $this->option('date-from'),
            $limit,
        ];
        $driver = DB::connection()->getDriverName();
        $explainSql = $driver === 'sqlite' ? "EXPLAIN QUERY PLAN {$sql}" : "EXPLAIN {$sql}";
        $rows = DB::select($explainSql, $bindings);

        $this->info("Driver: {$driver}");
        $this->table($this->headers($rows), $this->rows($rows));

        return self::SUCCESS;
    }

    /**
     * @param  list<object>  $rows
     * @return list<string>
     */
    private function headers(array $rows): array
    {
        if ($rows === []) {
            return ['result'];
        }

        return array_keys((array) $rows[0]);
    }

    /**
     * @param  list<object>  $rows
     * @return list<array<int, mixed>>
     */
    private function rows(array $rows): array
    {
        if ($rows === []) {
            return [['No explain rows returned.']];
        }

        return array_map(fn (object $row): array => array_values((array) $row), $rows);
    }
}
