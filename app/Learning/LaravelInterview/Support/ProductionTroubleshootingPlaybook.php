<?php

namespace App\Learning\LaravelInterview\Support;

class ProductionTroubleshootingPlaybook
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return [
            'slow-api' => [
                'title' => '接口突然变慢',
                'symptoms' => [
                    'P95/P99 延迟升高，错误率可能暂时不高。',
                    'PHP-FPM busy worker 增多，Nginx upstream response time 变长。',
                    '数据库、Redis 或第三方接口耗时同步上升。',
                ],
                'first_checks' => [
                    '按 trace id 拆分 controller、SQL、Redis、HTTP client、queue dispatch 耗时。',
                    '查看最近发布、配置变更、流量突增和下游告警。',
                    '确认慢接口是单接口、单租户、单机房还是全站问题。',
                ],
                'likely_causes' => [
                    '慢 SQL、N+1、大 offset 分页或缺失索引。',
                    '缓存失效、热 key、Redis 阻塞命令。',
                    '第三方接口超时或重试放大。',
                    'PHP-FPM worker 不足或队列任务挤占资源。',
                ],
                'remediation' => [
                    '先降级非核心依赖、限制重试、保护主链路。',
                    '对慢 SQL 补索引或改 keyset pagination。',
                    '恢复缓存、预热热点数据，必要时临时扩容。',
                    '把耗时副作用切到队列并保证任务幂等。',
                ],
                'prevention' => [
                    '核心接口保留 trace id、分层耗时和慢 SQL 告警。',
                    '压测覆盖缓存失效和下游慢响应场景。',
                    '发布前检查 explain、N+1、外部调用超时和重试策略。',
                ],
                'interview_answer' => '先判断影响面，再用链路耗时拆分瓶颈，优先止血保护主流程，随后定位 SQL、缓存、下游或 FPM 容量问题，最后沉淀监控和压测用例。',
            ],
            'mysql-slow-query' => [
                'title' => 'MySQL 慢查询',
                'symptoms' => [
                    '慢查询日志增长，数据库 CPU 或 IO 升高。',
                    '接口等待数据库连接，连接池或 max_connections 紧张。',
                    'EXPLAIN 出现全表扫描、filesort、临时表或扫描行数异常。',
                ],
                'first_checks' => [
                    '抓取 SQL、bindings、执行频次和调用入口。',
                    '运行 EXPLAIN，确认 type、key、rows、Extra。',
                    '检查表数据量、索引基数、where/order/group 字段顺序。',
                ],
                'likely_causes' => [
                    '联合索引顺序不匹配过滤和排序。',
                    '隐式类型转换、函数包裹索引列、左模糊 like。',
                    '深分页、大范围扫描或 N+1。',
                    '事务过长导致锁等待。',
                ],
                'remediation' => [
                    '补合适联合索引，优先让等值、范围、排序匹配执行计划。',
                    '改写 SQL，避免函数包裹索引列和隐式转换。',
                    '深分页改 keyset pagination。',
                    '缩短事务，把外部调用移出事务。',
                ],
                'prevention' => [
                    '上线前对核心 SQL 固化 explain 和数据量假设。',
                    '慢查询阈值、锁等待和连接数要有告警。',
                    '高频列表接口设计分页上限和必要索引。',
                ],
                'interview_answer' => '慢 SQL 排查不能只说加索引，要从 SQL 形态、执行计划、数据分布、调用频次和锁等待一起看。',
            ],
            'redis-hot-key' => [
                'title' => 'Redis 热 key / 大 key',
                'symptoms' => [
                    'Redis 单核 CPU 打满，延迟抖动。',
                    '某些接口依赖同一个缓存 key，流量集中。',
                    '网络流量异常或慢日志出现大对象读写。',
                ],
                'first_checks' => [
                    '通过监控、slowlog、bigkeys、业务日志定位 key。',
                    '确认 key 类型、大小、TTL、读写频率和调用入口。',
                    '判断是热 key、大 key，还是阻塞命令导致。',
                ],
                'likely_causes' => [
                    '热点配置、首页榜单、秒杀商品集中访问。',
                    'Hash/List/Set 过大导致单次序列化和网络传输过重。',
                    '缓存击穿后所有请求同时回源数据库。',
                ],
                'remediation' => [
                    '热 key 拆分、多级缓存、本地短 TTL 缓存。',
                    '大 key 拆分字段或分页读取，避免一次性取全量。',
                    '击穿场景加互斥锁、逻辑过期或后台刷新。',
                    '禁止线上执行 keys 等阻塞命令。',
                ],
                'prevention' => [
                    '设计 key 时明确单 key 最大体积和访问模型。',
                    '对热点业务做压测和缓存预热。',
                    '监控 Redis CPU、延迟、命中率、网络和大 key。',
                ],
                'interview_answer' => '热 key 重点是分散读压力，大 key 重点是降低单次操作体积，两者都要结合业务访问模型处理。',
            ],
            'mq-backlog' => [
                'title' => 'MQ 消息堆积',
                'symptoms' => [
                    '队列 ready/unacked 数持续增长。',
                    '业务状态延迟流转，通知或同步任务滞后。',
                    '消费者日志出现超时、异常重试或处理速度下降。',
                ],
                'first_checks' => [
                    '确认生产速度、消费速度、失败率和单条耗时。',
                    '检查消费者进程数量、prefetch、ack/nack 行为。',
                    '查看是否有毒消息反复重试阻塞队列。',
                ],
                'likely_causes' => [
                    '下游数据库或第三方接口变慢。',
                    '消费者并发不足或 worker 意外退出。',
                    '消息体过大、单条任务做太多同步操作。',
                    '失败重试无退避，造成重试风暴。',
                ],
                'remediation' => [
                    '先暂停非核心生产者或限流入口。',
                    '横向扩容消费者，必要时拆分队列。',
                    '把毒消息转入死信队列，避免阻塞主队列。',
                    '缩短单条任务耗时，补幂等后批量重放。',
                ],
                'prevention' => [
                    '队列长度、消费延迟、失败率、死信数量必须告警。',
                    '任务必须幂等，重试要有退避和最大次数。',
                    '核心队列和低优先级队列分离。',
                ],
                'interview_answer' => '消息堆积先看生产消费速率差，再看消费者错误和下游瓶颈；处理时要保护主链路，扩容消费前必须确认任务幂等。',
            ],
            'queue-worker-memory' => [
                'title' => 'Laravel Queue Worker 内存增长',
                'symptoms' => [
                    'queue:work 进程 RSS 持续升高。',
                    '任务处理一段时间后变慢或被 OOM kill。',
                    '部署后旧代码仍在 worker 中运行。',
                ],
                'first_checks' => [
                    '查看 worker 启动参数、内存限制、max-jobs、max-time。',
                    '检查任务中是否持有大集合、静态缓存、单例请求态对象。',
                    '确认部署流程是否执行 queue:restart。',
                ],
                'likely_causes' => [
                    '常驻进程不会像 FPM 请求结束一样释放全部状态。',
                    '单个任务一次性加载过多数据。',
                    '第三方 SDK、图片处理或导出任务泄漏内存。',
                ],
                'remediation' => [
                    '给 worker 设置 --memory、--max-jobs、--max-time。',
                    '大数据任务改 chunk/cursor，及时 unset 大对象。',
                    '部署后执行 queue:restart 平滑重启。',
                    '把重 CPU/重内存任务拆到独立队列。',
                ],
                'prevention' => [
                    '所有常驻进程都要有重启策略和内存告警。',
                    '任务设计时限制单批大小。',
                    'Octane/Swoole 同样要避免请求态数据污染单例。',
                ],
                'interview_answer' => 'Laravel worker 是常驻进程，必须用任务数、运行时间和内存上限做生命周期管理。',
            ],
            'http-502-504' => [
                'title' => 'HTTP 502 / 504',
                'symptoms' => [
                    'Nginx 返回 502 Bad Gateway 或 504 Gateway Timeout。',
                    '用户看到间歇性失败，应用日志可能没有完整请求日志。',
                    'FPM、上游服务或网络链路出现异常。',
                ],
                'first_checks' => [
                    '看 Nginx error log、access log upstream_status 和 request_time。',
                    '检查 PHP-FPM 是否存活、worker 是否打满、慢日志是否开启。',
                    '确认 upstream timeout、fastcgi timeout 和应用内 HTTP timeout。',
                ],
                'likely_causes' => [
                    '502 常见于 FPM 挂掉、socket 错误、上游连接失败。',
                    '504 常见于上游响应超过网关超时。',
                    '慢 SQL、外部 API 或死锁导致请求卡住。',
                ],
                'remediation' => [
                    '恢复 FPM 或回滚最近发布。',
                    '临时扩容 worker，但要同时确认数据库承载能力。',
                    '降低慢接口耗时，必要时异步化或降级。',
                    '统一网关、应用、下游的超时预算。',
                ],
                'prevention' => [
                    '配置 FPM status、slowlog 和 Nginx upstream 指标。',
                    '对外部调用设置合理 timeout 和 retry。',
                    '容量评估要同时看 FPM worker 和下游容量。',
                ],
                'interview_answer' => '502 先查上游是否可连接，504 先查上游为什么超时；两者都要结合 Nginx、FPM 和应用链路日志。',
            ],
            'cpu-spike' => [
                'title' => 'CPU 飙高',
                'symptoms' => [
                    '单机 load average 和 CPU 使用率升高。',
                    '接口耗时抖动，队列消费变慢。',
                    'Redis/MySQL/PHP 其中一个进程占用异常。',
                ],
                'first_checks' => [
                    'top/htop 定位进程，ps 查看命令和启动参数。',
                    '如果是 PHP，结合 slowlog、trace id、最近任务定位代码路径。',
                    '如果是 MySQL/Redis，结合慢查询或慢日志定位操作。',
                ],
                'likely_causes' => [
                    '死循环、正则灾难回溯、大数组处理。',
                    '慢 SQL 或 Redis 阻塞命令。',
                    '压缩、图片处理、导出等 CPU 密集任务挤占 Web 资源。',
                ],
                'remediation' => [
                    '先限流或隔离 CPU 密集任务。',
                    '终止异常进程前保留必要现场。',
                    '优化热点代码、SQL 或正则。',
                    '重 CPU 任务迁移到独立 worker 或专用服务。',
                ],
                'prevention' => [
                    '对 CPU 密集任务做资源隔离。',
                    '正则和批处理逻辑加入数据量上限。',
                    '建立进程级 CPU、load 和慢请求告警。',
                ],
                'interview_answer' => 'CPU 飙高要先定位进程，再定位代码路径；不能只重启，重启前要尽量保留现场和输入样本。',
            ],
            'disk-full' => [
                'title' => '磁盘写满',
                'symptoms' => [
                    '日志无法写入，上传失败，数据库或队列异常。',
                    'df 显示磁盘满，du 定位到日志、缓存或临时文件。',
                    'inode 也可能耗尽，表现为还有空间但无法创建文件。',
                ],
                'first_checks' => [
                    'df -h 和 df -i 分别看容量和 inode。',
                    'du -sh 定位大目录，优先看 storage/logs、临时目录、容器日志。',
                    '确认是否有日志风暴或异常重试导致快速增长。',
                ],
                'likely_causes' => [
                    '应用日志未轮转。',
                    '容器 json log 过大。',
                    '导出、上传或临时文件未清理。',
                    '异常任务疯狂写错误日志。',
                ],
                'remediation' => [
                    '清理可删除日志和临时文件，避免误删数据库文件。',
                    '压缩或截断超大日志，恢复服务写入。',
                    '修复日志风暴根因。',
                    '补 logrotate 或容器日志大小限制。',
                ],
                'prevention' => [
                    '磁盘容量和 inode 都要告警。',
                    '日志按环境配置采样、级别和轮转。',
                    '导出和临时文件要有过期清理任务。',
                ],
                'interview_answer' => '磁盘满先恢复写入能力，再定位增长源；生产环境清理前必须确认文件用途，避免误删数据。',
            ],
            'retry-storm' => [
                'title' => '重试风暴',
                'symptoms' => [
                    '下游故障后请求量反而倍增。',
                    '队列失败任务快速增加，第三方接口 QPS 异常。',
                    '多个服务同时重试，导致雪崩扩大。',
                ],
                'first_checks' => [
                    '确认重试次数、间隔、超时和调用链层级。',
                    '查看是否同时存在 HTTP client retry、队列 retry、网关 retry。',
                    '确认下游是否已经限流或部分不可用。',
                ],
                'likely_causes' => [
                    '无退避、无抖动、无最大重试时间。',
                    '每层都重试，乘法放大流量。',
                    '非幂等接口被重复调用。',
                ],
                'remediation' => [
                    '立即降低重试次数或关闭非核心重试。',
                    '加熔断、降级和限流，保护下游。',
                    '幂等校验后再补偿重放失败任务。',
                    '把同步强依赖改成异步最终一致。',
                ],
                'prevention' => [
                    '统一超时预算和重试预算。',
                    '重试必须指数退避和 jitter。',
                    '资损相关接口必须有业务幂等键。',
                ],
                'interview_answer' => '重试是放大器，不是兜底万能药；设计时必须有超时、退避、抖动、最大次数、幂等和熔断。',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function scenario(string $key): ?array
    {
        $scenarios = $this->all();

        return $scenarios[$key] ?? null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }
}
