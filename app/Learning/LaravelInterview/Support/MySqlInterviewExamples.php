<?php

namespace App\Learning\LaravelInterview\Support;

use Illuminate\Support\Facades\DB;

class MySqlInterviewExamples
{
    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $this->seedOrders();

        $isolation = DB::selectOne('select @@transaction_isolation as value')->value ?? 'unknown';

        $explainRows = DB::select(
            'EXPLAIN SELECT status, paid_at, order_no
             FROM interview_mysql_demo_orders
             WHERE status = ? AND paid_at IS NOT NULL
             ORDER BY paid_at DESC
             LIMIT 3',
            ['paid'],
        );

        $duplicateInsertCount = DB::table('interview_mysql_demo_orders')->insertOrIgnore([
            'order_no' => 'ORDER-1001',
            'user_id' => 1,
            'status' => 'paid',
            'amount_cents' => 9900,
            'paid_at' => now(),
            'metadata' => json_encode(['source' => 'duplicate-insert'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lockedOrder = DB::transaction(function (): array {
            $order = DB::table('interview_mysql_demo_orders')
                ->where('order_no', 'ORDER-1002')
                ->lockForUpdate()
                ->first();

            DB::table('interview_mysql_demo_orders')
                ->where('id', $order->id)
                ->update([
                    'status' => 'paid',
                    'paid_at' => $order->paid_at ?: now(),
                    'updated_at' => now(),
                ]);

            return [
                'order_no' => $order->order_no,
                'locked_status_before_update' => $order->status,
                'lock' => 'lockForUpdate',
                'retry_attempts' => 3,
            ];
        }, 3);

        $keysetPage = DB::table('interview_mysql_demo_orders')
            ->select(['id', 'order_no', 'status'])
            ->where('id', '>', 1)
            ->orderBy('id')
            ->limit(3)
            ->get()
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'order_no' => $row->order_no,
                'status' => $row->status,
            ])
            ->all();

        return [
            'table' => 'interview_mysql_demo_orders',
            'transaction_isolation' => $isolation,
            'explain' => array_map(fn (object $row): array => (array) $row, $explainRows),
            'unique_idempotency' => [
                'order_no' => 'ORDER-1001',
                'duplicate_insert_count' => $duplicateInsertCount,
                'point' => '唯一索引可以把重复请求收敛成 0 行插入，是幂等设计的底线之一。',
            ],
            'transaction_lock' => $lockedOrder,
            'keyset_pagination' => [
                'pattern' => 'where id > last_seen_id order by id limit n',
                'rows' => $keysetPage,
            ],
            'interview_points' => [
                '联合索引字段顺序应服务于等值过滤、范围过滤和排序。',
                '覆盖索引能减少回表，但会增加写入成本和索引维护成本。',
                '深分页应优先考虑 keyset pagination，避免大 offset 扫描。',
                '事务内只放必要数据库操作，避免外部 HTTP 调用拉长锁持有时间。',
                '唯一索引、事务和状态机通常一起构成资损类业务的幂等保护。',
            ],
        ];
    }

    private function seedOrders(): void
    {
        DB::table('interview_mysql_demo_orders')->truncate();

        DB::table('interview_mysql_demo_orders')->insert([
            [
                'order_no' => 'ORDER-1001',
                'user_id' => 1,
                'status' => 'paid',
                'amount_cents' => 9900,
                'paid_at' => now()->subMinutes(30),
                'metadata' => json_encode(['channel' => 'web'], JSON_UNESCAPED_UNICODE),
                'created_at' => now()->subHours(2),
                'updated_at' => now(),
            ],
            [
                'order_no' => 'ORDER-1002',
                'user_id' => 2,
                'status' => 'pending',
                'amount_cents' => 12900,
                'paid_at' => null,
                'metadata' => json_encode(['channel' => 'app'], JSON_UNESCAPED_UNICODE),
                'created_at' => now()->subHour(),
                'updated_at' => now(),
            ],
            [
                'order_no' => 'ORDER-1003',
                'user_id' => 3,
                'status' => 'paid',
                'amount_cents' => 25900,
                'paid_at' => now()->subMinutes(5),
                'metadata' => json_encode(['channel' => 'api'], JSON_UNESCAPED_UNICODE),
                'created_at' => now()->subMinutes(40),
                'updated_at' => now(),
            ],
            [
                'order_no' => 'ORDER-1004',
                'user_id' => 4,
                'status' => 'cancelled',
                'amount_cents' => 4900,
                'paid_at' => null,
                'metadata' => json_encode(['channel' => 'web'], JSON_UNESCAPED_UNICODE),
                'created_at' => now()->subMinutes(20),
                'updated_at' => now(),
            ],
        ]);
    }
}
