# Web 安全攻防实验

本文对应 `P2-04 安全攻防增强`，目标是把安全能力从“知道概念”推进到“有攻击样例、有代码入口、有测试证据、有生产风险说明”。

## 1. 代码入口

- SSRF URL 检查服务：`app/Support/Security/UrlSafetyInspector.php`
- SSRF 检查接口：`app/Http/Controllers/Api/V1/Security/UrlSafetyController.php`
- 审计敏感字段脱敏：`app/Support/Security/SensitiveDataMasker.php`
- 审计写入监听器：`app/Listeners/WriteAuditLog.php`
- 签名反重放中间件：`app/Http/Middleware/VerifyApiSignature.php`
- 操作日志中间件：`app/Http/Middleware/RecordOperationLog.php`
- 测试：`tests/Feature/PhaseThirteenSecurityAttackLabTest.php`

## 2. SSRF

接口：

```text
POST /api/v1/security/url-check
```

示例请求：

```json
{
  "url": "http://127.0.0.1/admin"
}
```

当前防护：

- 只允许 `http` 和 `https`。
- 禁止 `localhost`、`.localhost`、`.local`。
- 禁止 URL 中包含账号密码。
- 禁止非 `80/443` 端口。
- 解析 IP 后阻断私有网段、回环地址、链路本地地址和保留地址。

攻击路径：

- 远程数据源导入时，攻击者传入 `http://127.0.0.1:3306` 探测内网。
- Webhook 回调地址被设置为云厂商 metadata 地址。
- 文件导入 URL 指向内网管理后台或 Redis、Elasticsearch 等未授权服务。

生产补强：

- 建议使用域名 allowlist，而不是只靠 blacklist。
- DNS 解析和实际请求之间存在 DNS rebinding 风险，生产请求前应再次解析并绑定目标 IP。
- 出网访问应走隔离代理，由代理执行 egress policy。

## 3. XSS

当前项目入口：

- `MetricUpsertRequest` 对 `name` 和 `description` 增加 `not_regex:/<\s*script/i`。
- Vue 默认使用文本插值，避免把用户输入当作 HTML 渲染。

攻击样例：

```json
{
  "name": "Security XSS Metric",
  "description": "<script>alert(document.cookie)</script>"
}
```

测试：

```bash
php artisan test --filter=PhaseThirteenSecurityAttackLabTest
```

生产补强：

- 富文本不能只用正则过滤，应使用 HTML sanitizer 和标签白名单。
- 管理后台展示审计日志、导入失败 payload、外部接口错误时，要统一走转义输出。
- CSP 可以降低 XSS 成功后的影响面，但不能替代输入验证和输出转义。

## 4. SQL 注入

当前项目使用 Eloquent 和 Query Builder 参数绑定：

- 指标查询的 `keyword` 进入 `where like`，由框架绑定参数。
- 排序字段使用白名单，避免 `order by` 字段注入。
- `MetricUpsertRequest` 和 Controller validate 限制类型、枚举和长度。

攻击样例：

```text
/api/v1/metrics?keyword=' OR 1=1 --
```

面试表达：

- 参数绑定能防值注入，但不能自动保护字段名、表名、排序方向等 SQL 片段。
- 当前项目的排序字段和方向都做了白名单，是为了防止结构注入。
- `whereRaw()`、`orderByRaw()` 和动态拼接 SQL 必须非常谨慎。

## 5. CSRF

当前项目使用 Laravel Web Session：

- 登录态 API 通过 `auth:web` 识别当前用户。
- Web 表单和 Inertia 页面依赖 Laravel CSRF 机制。
- `/api/v1/security/signed-echo` 是独立签名接口，不依赖 Session。

生产补强：

- SameSite Cookie 建议使用 `lax` 或更严格策略。
- 对敏感操作增加二次确认、签名、短期 token 或重新输入密码。
- 前后端分离时要明确 Session Cookie、CSRF Token、CORS 的边界。

## 6. 反序列化

当前项目没有接收用户提供的 PHP 序列化字符串。

风险场景：

- 把外部输入传给 `unserialize()`。
- 队列、缓存或会话中保存不可信对象。
- 第三方包存在可利用的 magic method 链。

生产规则：

- 不对用户输入使用 `unserialize()`。
- 必须反序列化时使用 `allowed_classes=false` 或明确 class allowlist。
- 外部数据优先使用 JSON，并配合 schema validation。

## 7. 文件上传

当前 CSV 导入：

- 使用 Laravel `file`、`mimes:csv,txt`、`max:10240` 校验。
- 文件存储到私有磁盘路径。
- 原始文件名只作为记录字段，不作为最终存储路径。

为什么只校验扩展名不够：

- 攻击者可以构造双扩展名，如 `payload.php.csv`。
- MIME 可能被伪造。
- CSV 可能包含公式注入，例如 `=HYPERLINK(...)`。
- 大文件可能导致内存、磁盘或队列资源耗尽。

生产补强：

- 检查 MIME、扩展名、文件头和解析结果。
- 存储到非 Web 可执行目录。
- 导出 CSV 时对 `= + - @` 开头的单元格做转义，避免公式注入。
- 上传、解析和入库拆成异步任务，并设置大小、行数和超时限制。

## 8. 审计脱敏

新增 `SensitiveDataMasker` 后，审计 metadata 中以下字段会被替换为 `[FILTERED]`：

- `password`
- `password_confirmation`
- `token`
- `api_token`
- `access_token`
- `refresh_token`
- `secret`
- `signature`
- `authorization`
- `cookie`

示例：

```php
[
    'password' => 'plain-text-password',
    'profile' => [
        'api_token' => 'secret-token',
        'safe_field' => 'visible',
    ],
]
```

会写入：

```php
[
    'password' => '[FILTERED]',
    'profile' => [
        'api_token' => '[FILTERED]',
        'safe_field' => 'visible',
    ],
]
```

## 9. 签名与反重放

当前签名明文：

```text
METHOD|/api/v1/security/signed-echo|timestamp|nonce|raw_body
```

当前策略：

- 时间戳 300 秒窗口。
- nonce 缓存 300 秒。
- 重复 nonce 返回 409。
- 签名不匹配返回 401。

资深追问：

- nonce 写入缓存失败怎么办？
- 多机部署时 nonce 存储必须共享吗？
- timestamp 窗口太大或太小分别有什么问题？
- HMAC secret 如何轮换？
- body canonicalization 不一致会不会导致误判？

## 10. 验收命令

```bash
php artisan test --filter=PhaseFiveSecurityAuditTest
php artisan test --filter=PhaseThirteenSecurityAttackLabTest
php artisan test --filter=PhaseTwelveOpenApiContractTest
```

验收标准：

- SSRF 本地和私有地址被拒绝，公网 HTTP(S) 目标可通过。
- XSS payload 被请求验证拦截。
- SQL 注入样式 keyword 被当成普通查询数据处理。
- 审计 metadata 中敏感字段被脱敏。
- OpenAPI 覆盖新增安全接口。
