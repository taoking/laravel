<?php

namespace App\Modules\Query\Services;

use App\Modules\Dataset\Models\Dataset;
use App\Modules\DataSource\Services\DataSourceConnectionFactory;
use App\Modules\Query\DTO\CompiledQuery;
use App\Modules\Query\DTO\QueryExecutionResult;

class QueryExecutor
{
    public function __construct(
        private readonly DataSourceConnectionFactory $connectionFactory,
        private readonly QueryDriverManager $driverManager,
    ) {}

    public function execute(Dataset $dataset, CompiledQuery $query): QueryExecutionResult
    {
        $dataset->loadMissing('dataSource');
        $startedAt = microtime(true);
        $connection = $this->connectionFactory->make($dataset->dataSource);

        try {
            $rows = $this->driverManager->driver($dataset->dataSource)->execute($connection, $query);
        } finally {
            $this->connectionFactory->disconnect($dataset->dataSource);
        }

        return new QueryExecutionResult(
            rows: $rows,
            elapsedMs: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }
}
