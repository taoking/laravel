# 性能优化与压测 Runbook

## 当前性能入口

- 指标查询 API：`GET /api/v1/metrics`
- 指标详情 API：`GET /api/v1/metrics/{id}`
- 压测脚本：`scripts/bench/wrk-metrics.sh`
- 慢 SQL 示例：`docs/database/metric-query-explain.md`

## 压测命令

```bash
BASE_URL=http://127.0.0.1:8000 scripts/bench/wrk-metrics.sh
```

如果接口需要登录态，先从浏览器或 curl 取得 cookie，再传入：

```bash
COOKIE_HEADER="laravel-session=xxx" scripts/bench/wrk-metrics.sh
```

## Laravel 优化命令

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

## PHP-FPM 与 OPcache

- OPcache 配置样例：`docker/php/opcache.ini`
- Web 项目通常优先开启 OPcache，JIT 对典型 IO 密集接口收益有限。
- FPM 进程数估算要结合单请求平均内存、CPU 核数和数据库连接容量。

## 慢接口排查顺序

1. 看响应时间分布和 95/99 分位。
2. 查 Laravel 日志、异常和 trace_id。
3. 查数据库慢 SQL 和 Explain。
4. 查 Redis 命中率、热点 key 和大 key。
5. 查队列堆积和失败任务。
6. 查 PHP-FPM slowlog、Nginx 499/502/504。

## 当前项目优化点

- 指标详情使用缓存：`metrics:detail:{id}`。
- 指标列表排序字段白名单，避免动态 order by 注入。
- 指标列表通过 Eager Loading 加载分类、最新值、地区和频率。
- CSV 导入使用 `LazyCollection` 逐行读取，避免一次性读入内存。
