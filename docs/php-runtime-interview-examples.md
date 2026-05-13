# PHP Runtime Interview Examples

本文档对应长期计划中的 PHP 运行机制主线，用于复盘 Composer autoload、OPcache、JIT、GC、FPM/CLI 差异、常驻进程内存风险。

## 学习目标

- 能区分 Composer 自动加载和 OPcache。
- 能说明 OPcache 如何减少 PHP 文件解析和编译成本。
- 能解释 JIT 为什么通常不是 Laravel 慢接口的第一优化点。
- 能说清 PHP 引用计数、GC 和常驻进程内存增长的关系。
- 能排查 CLI 与 FPM PHP 版本、扩展、ini 配置不一致的问题。

## 源码入口

- 示例逻辑：`app/Learning/LaravelInterview/Support/PhpRuntimeInterviewExamples.php`
- Artisan 命令：`app/Learning/LaravelInterview/Console/InterviewPhpRuntimeCommand.php`
- 测试：`tests/Feature/InterviewPhpRuntimeCommandTest.php`

## 运行方式

```bash
docker compose exec laravel.test php artisan interview:php-runtime
```

输出完整 JSON：

```bash
docker compose exec laravel.test php artisan interview:php-runtime --json
```

## 覆盖内容

- PHP 版本、SAPI、内存限制、扩展加载状态。
- Composer `vendor/autoload.php` 和 PSR-4 映射。
- OPcache enable、CLI enable、内存、时间戳校验。
- JIT 配置和 Laravel Web 请求中的收益边界。
- GC 状态、引用计数、循环引用和常驻进程风险。
- PHP-FPM 和 CLI worker 生命周期差异。

## 面试问答

### Composer autoload 和 OPcache 有什么区别？

Composer autoload 解决“类名如何找到文件”的问题；OPcache 解决“PHP 文件每次请求都要解析编译”的成本。生产环境通常两者都需要优化。

### OPcache 为什么能提升性能？

PHP 文件会被解析并编译成 opcode。OPcache 把 opcode 缓存在共享内存中，后续请求可以复用，减少文件解析和编译成本。

### JIT 能显著提升 Laravel 性能吗？

通常不能作为第一优化点。Laravel Web 请求大多是 IO 密集型，瓶颈常见于 SQL、Redis、HTTP 调用、模板渲染和序列化。JIT 更可能影响 CPU 密集型计算。

### PHP 内存为什么还会泄漏？

普通 FPM 请求结束后大部分请求态状态会释放；但 queue worker、schedule worker、Octane 是常驻进程，如果静态变量、单例、大集合或第三方 SDK 持有对象，就可能持续增长。

### CLI 和 FPM 为什么要分别排查？

CLI 和 FPM 可能使用不同 php.ini、扩展、环境变量和用户权限。`php -m` 看到的扩展不代表 FPM 一定也加载。

## 生产实践提示

- 发布流程要明确 OPcache reload 或 FPM reload。
- `composer dump-autoload -o` 适合生产构建阶段。
- 慢接口先拆链路耗时，再判断是否 PHP CPU 瓶颈。
- 常驻 worker 要设置内存限制、最大任务数和最大运行时间。
- 线上排查同时确认 `php -v`、FPM status、扩展和 ini 配置。
