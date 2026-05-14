# Laravel Middleware Pipeline 源码追问

## 项目入口

- `bootstrap/app.php`
- `app/Http/Middleware/EnsureUserHasPermission.php`
- `app/Http/Middleware/VerifyApiSignature.php`
- `app/Http/Middleware/RecordOperationLog.php`
- `routes/api.php`

## 源码类

- `Illuminate\Pipeline\Pipeline`
- `Illuminate\Routing\Pipeline`
- `Illuminate\Routing\Router`
- `Illuminate\Foundation\Configuration\Middleware`

## 执行链

以 `GET /api/v1/metrics` 为例：

```text
Request
  -> EnsureTraceId
  -> RecordOperationLog
  -> api group middleware
  -> auth:web
  -> permission:metrics.view
  -> throttle:metrics-query
  -> MetricController@index
  -> Response 反向穿过 middleware
```

Middleware 是洋葱模型。请求进入时按顺序执行，响应返回时按相反顺序回到上层。

## 当前项目例子

- `EnsureUserHasPermission`：检查用户是否拥有权限，无权限时 API 返回 403。
- `VerifyApiSignature`：校验 HMAC 签名、时间戳和 nonce，防重放。
- `RecordOperationLog`：请求结束后记录操作日志。
- `EnsureTraceId`：为每次请求写入 trace id。

## 异常与响应

中间件有两类返回方式：

- 直接短路返回 Response，例如无权限返回 403。
- 调用 `$next($request)` 继续向内层传递。

如果控制器或内层中间件抛异常，异常会进入 `bootstrap/app.php` 的 `withExceptions()` 配置，由异常渲染逻辑生成 API JSON 响应。

## 生产风险

- 中间件顺序错误会导致鉴权、限流、审计失效。
- 记录操作日志时要避免保存敏感字段。
- 限流粒度要按用户、IP 或业务 Key 设计。
- 中间件里不要执行大查询或慢外部调用。

## 基础问题

1. Middleware 洋葱模型是什么？
2. `$next($request)` 的作用是什么？
3. 路由中间件和全局中间件有什么区别？
4. 当前项目权限中间件如何返回 403？
5. 限流中间件在哪里配置？

## 资深追问

1. Middleware 抛异常后响应如何生成？
2. `RecordOperationLog` 为什么适合放在请求外层？
3. `auth:web` 和 `permission:*` 顺序反了会怎样？
4. 多个中间件都修改响应头时如何判断最终结果？
5. 如何避免审计日志中间件记录密码、token 等敏感字段？
