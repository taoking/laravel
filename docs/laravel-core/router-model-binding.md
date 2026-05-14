# Laravel Router 与 Route Model Binding 源码追问

## 项目入口

- `routes/api.php`
- `app/Http/Controllers/Api/V1/Metrics/MetricController.php`
- `app/Http/Controllers/Api/V1/Imports/ImportTaskController.php`
- `app/Http/Controllers/Api/V1/RoleController.php`

## 源码类

- `Illuminate\Routing\Router`
- `Illuminate\Routing\Route`
- `Illuminate\Routing\RouteCollection`
- `Illuminate\Routing\ImplicitRouteBinding`
- `Illuminate\Routing\ControllerDispatcher`

## 执行链

以 `GET /api/v1/metrics/{metric}` 为例：

```text
Router 匹配 URI 和 HTTP Method
  -> 找到 Route 对象
  -> 收集中间件
  -> 执行 Route Model Binding
  -> {metric} 解析为 App\Domains\Metrics\Models\Metric
  -> ControllerDispatcher 调用 MetricController@show
```

如果 URL 中的 ID 找不到模型，Laravel 会抛出 404。

## 当前项目例子

- `MetricController@show(Metric $metric, ...)`
- `MetricController@update(MetricUpsertRequest $request, Metric $metric, ...)`
- `ImportTaskController@show(ImportTask $import)`
- `RoleController@update(Role $role)`

这些控制器不需要手动 `Metric::findOrFail($id)`，因为隐式 Route Model Binding 已经完成。

## 路由缓存

生产环境常用：

```bash
php artisan route:cache
php artisan route:clear
```

当前项目路由主要是控制器动作，适合缓存。不要在需要 route cache 的项目中使用不可序列化的复杂路由闭包。

## 生产风险

- Route Model Binding 会自动查库，列表接口不要误用绑定造成额外查询。
- 软删除模型默认不会绑定到已删除记录，除非显式配置。
- 路由参数名要和控制器参数匹配，例如 `{metric}` 对应 `$metric`。
- 路由缓存后新增路由必须重新缓存或清理。

## 基础问题

1. Laravel 路由匹配依据是什么？
2. Route Model Binding 解决了什么问题？
3. `{metric}` 为什么能变成 `Metric $metric`？
4. 模型不存在时返回什么？
5. 生产环境为什么要做 route cache？

## 资深追问

1. Route Model Binding 与 Policy、Middleware 的执行顺序如何理解？
2. 软删除模型绑定时有哪些坑？
3. 路由闭包为什么会影响 route cache？
4. 绑定模型会不会导致 N+1？
5. 如何为 code 字段而不是 id 字段做模型绑定？
