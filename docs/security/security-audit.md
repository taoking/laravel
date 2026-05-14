# 安全与审计实现说明

Phase 5 当前实现了限流、签名反重放、审计日志、上传校验和缓存失效。P2-04 继续补充 SSRF URL 检查、审计 metadata 脱敏和 Web 攻击样例，详细攻击路径见 `docs/security/web-attack-labs.md`。

## 已实现入口

- 指标查询限流：`GET /api/v1/metrics`
- 签名反重放示例：`POST /api/v1/security/signed-echo`
- SSRF URL 检查示例：`POST /api/v1/security/url-check`
- 非法上传校验：`POST /api/v1/imports`
- 操作日志表：`operation_logs`
- 审计日志表：`audit_logs`
- 审计事件：`App\Events\AuditEvent`
- 审计监听器：`App\Listeners\WriteAuditLog`
- 审计脱敏：`App\Support\Security\SensitiveDataMasker`
- SSRF 检查：`App\Support\Security\UrlSafetyInspector`

## 签名算法

签名明文：

```text
METHOD|/api/v1/security/signed-echo|timestamp|nonce|raw_body
```

签名：

```text
hash_hmac('sha256', plaintext, API_SIGNATURE_SECRET)
```

请求头：

- `X-Timestamp`
- `X-Nonce`
- `X-Signature`

安全策略：

- 时间戳允许 300 秒误差。
- nonce 写入缓存 300 秒，同一 nonce 重复请求返回 409。
- 签名错误返回 401。

## 审计日志字段

- `user_id`
- `action`
- `resource_type`
- `resource_id`
- `ip_address`
- `trace_id`
- `user_agent`
- `metadata`

审计 metadata 写入前会递归脱敏敏感字段，例如 `password`、`token`、`api_token`、`secret`、`signature`、`authorization` 和 `cookie`。

## SSRF URL 检查

当前 `POST /api/v1/security/url-check` 用于演示远程数据源、Webhook 或外部文件导入前的 URL 安全检查。

阻断策略：

- 非 `http/https` scheme。
- `localhost`、`.localhost`、`.local`。
- URL 中包含用户名或密码。
- 非 `80/443` 端口。
- 私有网段、回环地址、链路本地地址和保留地址。

生产建议：

- 对远程数据源优先使用 allowlist。
- 防 DNS rebinding：实际请求前再次解析，并确保请求目标 IP 与检查结果一致。
- 出网请求通过统一代理执行 egress policy。

## 面试表达

- 限流用 Laravel RateLimiter，在路由层控制接口访问频率。
- 反重放由 `timestamp + nonce + HMAC` 组成，nonce 进入缓存并设置 TTL。
- 审计日志不信任前端传入用户，统一从认证上下文和请求上下文获取。
- 指标详情缓存通过写操作显式失效，避免返回旧数据。
- 热点指标使用 Redis ZSet 记录，测试环境自动降级到 Cache。
- Web 输出默认依赖 Vue/Blade 转义，指标写入入口同时拒绝 `<script>` 片段作为额外保护。
- SSRF 防护不能只检查字符串，要解析 scheme、host、port 和解析后的 IP。
- 审计日志可以用于追踪事故，但不能把密码、token、签名等敏感值原样写入。
- SQL 注入防护除了参数绑定，还要对白名单字段如排序字段、方向和表名做显式限制。
