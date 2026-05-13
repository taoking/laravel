<?php

namespace App\Learning\LaravelInterview\Support;

class SystemDesignInterviewPlaybook
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return [
            'payment-callback' => [
                'title' => '支付回调最终一致',
                'requirements' => [
                    '第三方支付多次回调时订单只能成功一次。',
                    '必须校验签名、金额、商户号、订单归属和状态流转。',
                    '支付成功后异步触发发货、通知、积分、对账等副作用。',
                ],
                'core_tables' => [
                    'orders(id, order_no, user_id, status, amount_cents, paid_at)',
                    'payment_transactions(id, order_no, gateway, transaction_no, amount_cents, status)',
                    'idempotency_keys(key, business_type, business_id, created_at)',
                    'outbox_events(id, aggregate_type, aggregate_id, event_type, payload, published_at)',
                ],
                'flow' => [
                    '接收原始 body，先验签和校验时间窗口。',
                    '用 transaction_no 或业务幂等键做唯一约束。',
                    '事务内锁定订单，校验金额和状态，只允许 pending -> paid。',
                    '同一事务写支付流水、订单状态和 outbox event。',
                    '后台发布 outbox event，驱动发货、通知和对账。',
                ],
                'risks' => [
                    '回调重复导致重复发货或重复入账。',
                    '先发 MQ 后提交事务，消费者读不到订单新状态。',
                    '第三方成功但本地失败，需要对账和补偿。',
                ],
                'interview_answer' => '支付回调核心是验签、防重放、幂等、事务状态机、outbox 和对账补偿，不能只靠第三方回调次数。',
            ],
            'inventory-deduction' => [
                'title' => '高并发扣库存',
                'requirements' => [
                    '不能超卖，库存扣减要可追溯。',
                    '下单、支付、取消、超时关闭都要影响库存状态。',
                    '热点商品要保护数据库和缓存。',
                ],
                'core_tables' => [
                    'products(id, sku, stock, locked_stock)',
                    'stock_records(id, sku, order_no, change, type, unique_key)',
                    'orders(id, order_no, status)',
                ],
                'flow' => [
                    '低并发可用数据库条件更新：stock >= quantity。',
                    '高并发可用 Redis 预扣库存，再异步落库和补偿。',
                    '库存流水使用唯一键保证同一订单不重复扣减。',
                    '订单取消或超时关闭释放 locked_stock。',
                ],
                'risks' => [
                    '缓存库存和数据库库存不一致。',
                    '支付成功但库存释放导致资损。',
                    '热点 SKU 打爆单行或单 key。',
                ],
                'interview_answer' => '扣库存要区分拍下减库存和支付减库存，核心是条件更新、防重、库存流水、补偿和热点保护。',
            ],
            'order-timeout' => [
                'title' => '订单超时关闭',
                'requirements' => [
                    '未支付订单超过时间自动关闭。',
                    '关闭要释放库存、优惠券和活动名额。',
                    '支付成功和超时关闭并发时不能状态倒退。',
                ],
                'core_tables' => [
                    'orders(id, order_no, status, expires_at, paid_at, closed_at)',
                    'order_state_logs(id, order_no, from_status, to_status, reason)',
                ],
                'flow' => [
                    '创建订单时写 expires_at。',
                    '延迟消息或定时扫描触发关闭。',
                    '关闭时用条件更新：where status = pending and expires_at <= now。',
                    '释放库存和优惠券要幂等。',
                    '支付回调只允许 pending -> paid，关闭只允许 pending -> closed。',
                ],
                'risks' => [
                    '延迟消息丢失导致订单不关闭。',
                    '支付和关闭并发导致状态倒退。',
                    '释放资源重复执行。',
                ],
                'interview_answer' => '订单超时关闭要靠状态机和条件更新保证并发安全，延迟消息只是触发器，不是唯一正确性来源。',
            ],
            'notification-center' => [
                'title' => '消息通知中心',
                'requirements' => [
                    '支持站内信、邮件、短信、Push 等多渠道。',
                    '不同业务可以模板化、异步化、限流和重试。',
                    '用户退订和发送结果要可追踪。',
                ],
                'core_tables' => [
                    'notification_templates(id, channel, code, content, version)',
                    'notification_tasks(id, user_id, channel, template_code, status, dedupe_key)',
                    'notification_logs(id, task_id, provider, status, response)',
                ],
                'flow' => [
                    '业务系统只发 notification event，不直接调用短信或邮件 SDK。',
                    '通知中心按模板渲染并生成发送任务。',
                    '不同渠道拆队列，失败按错误类型重试或转人工。',
                    '使用 dedupe_key 避免重复通知。',
                ],
                'risks' => [
                    '短信供应商慢响应拖垮主链路。',
                    '模板变更影响历史消息复盘。',
                    '重复通知造成用户骚扰。',
                ],
                'interview_answer' => '通知中心要解耦业务和渠道，核心是模板版本、异步队列、去重、限流、失败重试和退订策略。',
            ],
            'admin-rbac' => [
                'title' => '后台 RBAC 权限',
                'requirements' => [
                    '支持用户、角色、权限、菜单和数据范围。',
                    '权限变更要实时或可控生效。',
                    '敏感操作要审计。',
                ],
                'core_tables' => [
                    'admin_users(id, name, status)',
                    'roles(id, name)',
                    'permissions(id, code, action, resource)',
                    'role_permissions(role_id, permission_id)',
                    'admin_operation_logs(id, admin_id, action, resource, ip, payload)',
                ],
                'flow' => [
                    '认证确认管理员身份。',
                    '授权按 permission code 校验动作。',
                    '数据范围按组织、门店、租户或资源归属过滤查询。',
                    '权限缓存按管理员或角色维度失效。',
                    '敏感操作写审计日志。',
                ],
                'risks' => [
                    '只控制菜单，不控制接口。',
                    '有动作权限但没有数据范围过滤。',
                    '超级管理员权限滥用不可追踪。',
                ],
                'interview_answer' => 'RBAC 不只是角色权限表，还要覆盖接口鉴权、数据范围、缓存失效和审计日志。',
            ],
            'audit-log' => [
                'title' => '审计日志与操作追踪',
                'requirements' => [
                    '关键操作可追溯到人、时间、IP、请求和数据变化。',
                    '日志不能泄漏敏感信息。',
                    '审计数据要防篡改并支持查询。',
                ],
                'core_tables' => [
                    'audit_logs(id, actor_id, action, resource_type, resource_id, before, after, trace_id)',
                    'login_logs(id, user_id, ip, user_agent, result)',
                ],
                'flow' => [
                    '中间件生成 trace_id 并贯穿日志。',
                    '服务层或领域事件记录关键状态变更。',
                    '敏感字段进入日志前脱敏。',
                    '重要审计日志异步写入独立存储或追加型日志。',
                ],
                'risks' => [
                    '日志过大影响主流程性能。',
                    '记录敏感明文造成二次泄漏。',
                    '只记录结果，不记录操作者和请求来源。',
                ],
                'interview_answer' => '审计日志要能支持追责和复盘，设计时要处理 trace、脱敏、异步、不可篡改和查询效率。',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function case(string $key): ?array
    {
        $cases = $this->all();

        return $cases[$key] ?? null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }
}
