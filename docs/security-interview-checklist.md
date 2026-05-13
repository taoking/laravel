# Security Interview Checklist

本文档对应长期计划中的 Laravel/PHP 安全主线，用于复盘认证、授权、SQL 注入、XSS、CSRF、SSRF、文件上传、日志脱敏、Webhook 签名和限流防刷。

## 学习目标

- 能区分认证、授权、数据归属和审计日志的职责。
- 能说清 Laravel 默认防护能力和仍需业务手动处理的边界。
- 能把安全问题落到可执行检查点，而不是只背漏洞名。
- 能在面试中结合真实业务，例如支付回调、后台权限、文件上传、短信验证码和导出接口。

## 源码入口

- 安全清单：`app/Learning/LaravelInterview/Support/SecurityInterviewChecklist.php`
- Artisan 命令：`app/Learning/LaravelInterview/Console/InterviewSecurityCommand.php`
- 命令注册：`app/Learning/LaravelInterview/Providers/InterviewExampleServiceProvider.php`
- 测试：`tests/Feature/InterviewSecurityCommandTest.php`

## 运行方式

列出所有安全主题：

```bash
docker compose exec laravel.test php artisan interview:security
```

查看单个主题：

```bash
docker compose exec laravel.test php artisan interview:security ssrf
```

输出 JSON：

```bash
docker compose exec laravel.test php artisan interview:security sql-injection --json
```

## 当前覆盖主题

- `authentication`：密码哈希、Session cookie、token 生命周期、登录限流。
- `authorization`：Gate、Policy、数据归属、IDOR、后台权限。
- `sql-injection`：参数绑定、raw SQL、排序字段白名单、最小数据库权限。
- `xss`：Blade escape、富文本净化、CSP、Cookie http_only。
- `csrf`：CSRF token、SameSite、状态变更方法、API token 边界。
- `ssrf`：URL 白名单、内网地址拦截、重定向、出口限制。
- `file-upload`：MIME、大小、重命名、存储隔离、资源消耗限制。
- `secrets-logging`：`.env`、日志脱敏、异常上报、队列 payload。
- `webhook-signature`：原始 body 验签、防重放、hash_equals、幂等状态机。
- `rate-limit`：RateLimiter、多维度限流、防刷和昂贵接口保护。

## 高频面试问答

### Laravel 使用 Eloquent 是否一定不会 SQL 注入？

不是。Eloquent 和 Query Builder 的值绑定能防大多数值注入，但 `selectRaw`、`orderByRaw`、动态字段名、排序方向、表名等不能简单绑定，必须白名单。

### 认证和授权有什么区别？

认证确认用户身份，授权确认用户是否能执行某个动作或访问某份数据。后台系统里常见漏洞不是没登录，而是已登录用户越权访问别人的资源。

### XSS 为什么不能只靠输入过滤？

不同输出上下文需要不同编码策略。HTML、属性、URL、JavaScript 字符串的处理方式不同；富文本还需要白名单净化。

### SSRF 如何在 PHP 项目中出现？

图片抓取、URL 预览、Webhook 测试、远程文件导入等功能都可能让服务端按用户输入发请求。防护必须校验协议、解析后的 IP、端口、重定向和出口网络。

### 支付回调验签后是否就安全？

不够。还要防重放、校验金额和商户号、保证订单归属、使用幂等键和状态机，避免重复回调造成重复入账或状态倒退。

## 生产实践提示

- 所有敏感操作都要服务端鉴权，前端隐藏按钮不算安全控制。
- Raw SQL、动态排序、导出接口、上传入口、Webhook 是 Laravel 项目安全高发点。
- 日志脱敏要覆盖请求、异常、队列、第三方 SDK 和调试工具。
- 限流不是只按 IP，关键接口要结合用户、设备、业务 key 和成本。
- 安全复盘要沉淀成 FormRequest、Policy、Middleware、Service 和测试用例。
