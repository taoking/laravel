# Laravel Interview Examples

这组文件是用于 `git diff` 阅读的 Laravel 组件样例，不默认接入现有业务入口。

## 覆盖内容

- 路由与 Controller：`routes/interview_examples.php`、`InterviewExamplesController`
- FormRequest 验证：`StorePostRequest`
- Resource 响应转换：`PostResource`
- Middleware：`EnsureInterviewToken`
- Service Container 与 ServiceProvider：`InterviewExampleServiceProvider`
- 接口依赖与服务层：`PaymentGateway`、`FakePaymentGateway`、`OrderCheckoutService`
- Eloquent：`Post`、`Comment` 的关系、scope、cast、accessor/mutator、软删除
- Query Builder、事务、悲观锁、缓存、限流、Storage、HTTP Client：`LaravelComponentCheatsheet`
- Event / Listener：`OrderPaid`、`WriteOrderPaidAuditLog`
- Queue Job：`SendInterviewWelcomeMail`
- Mail / Notification：`InterviewWelcomeMail`、`InterviewReminderNotification`
- Blade 邮件模板：`resources/views/emails/interview-welcome.blade.php`
- Policy：`PostPolicy`
- Artisan Command：`InterviewDigestCommand`
- Migration：`interview_example_posts`、`interview_example_comments`

## 本地启用方式

如果只是查看样例，不需要做任何接入。示例 Provider 已注册，但路由默认关闭；Artisan 摘要命令可直接使用：

```bash
docker compose exec laravel.test php artisan interview:digest
docker compose exec laravel.test php artisan interview:infra-check
```

若要本地访问 `/interview-examples`，在 `.env` 中打开：

```dotenv
INTERVIEW_EXAMPLES_ENABLED=true
INTERVIEW_EXAMPLES_TOKEN=demo-token
```

然后重启应用容器或重新执行 Artisan 命令：

```bash
docker compose up -d laravel.test
docker compose exec laravel.test php artisan route:list --path=interview-examples
```

## 推荐查看命令

```bash
git diff -- app/Learning/LaravelInterview routes/interview_examples.php config/interview_examples.php database/migrations docs/laravel-interview-examples.md tests/Feature/InterviewExamplesRoutingTest.php
```
