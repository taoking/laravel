# 导入导出 Worker 说明

本文记录 Phase 4 异步任务入口、幂等策略和本地/生产运行命令。

## API 入口

- 创建导入任务：`POST /api/v1/imports`
- 导入任务列表：`GET /api/v1/imports`
- 导入任务详情：`GET /api/v1/imports/{id}`
- 重试导入任务：`POST /api/v1/imports/{id}/retry`
- 创建导出任务：`POST /api/v1/exports`

## CSV 导入格式

```csv
metric_code,region_code,frequency_code,period_date,period_label,value,source
revenue_amount,CN-SH,monthly,2026-05-01,2026-05,1300000,test
```

必填字段：

- `metric_code`
- `region_code`
- `frequency_code`
- `period_date`
- `period_label`
- `value`

## 幂等策略

- 请求头：`Idempotency-Key`
- 同一个幂等键重复提交时，直接返回已有任务，不重复入库、不重复派发。
- Job 重试前会检查任务状态；已完成任务不会被重复处理。
- `POST /api/v1/imports/{id}/retry` 会清理失败记录并重新派发 Job。

## Worker 命令

本地：

```bash
php artisan queue:work --tries=3 --timeout=120
```

生产 Supervisor 示例：

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
stopwaitsecs=3600
```

## 定时任务

- 命令：`php artisan metrics:daily-summary`
- 调度：`routes/console.php` 中每日 `01:00` 执行，并启用 `withoutOverlapping()`。
