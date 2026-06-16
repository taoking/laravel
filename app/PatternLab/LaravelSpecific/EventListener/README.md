# Event / Listener 事件监听模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成事件监听模式代码实现。

## 练习内容

**具体功能：订单支付成功事件分发**

你正在实现 Laravel 风格的事件监听练习，订单支付成功后通过事件触发通知、积分和审计日志。

具体要求：

- 设计事件对象承载订单编号、用户编号和支付金额。
- 设计多个监听器分别处理通知、积分和审计日志。
- 在 Exercise.php::run() 中演示构造事件并列出将要触发的监听器。
- 补充测试验证新增监听器不需要修改支付确认服务。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- OrderPaid.php
- SendOrderPaidNotification.php
- GrantOrderPoints.php
- WritePaymentAuditLog.php

## 运行入口

```bash
php artisan pattern-lab:show event-listener
```

## 测试入口

```bash
php artisan test --filter EventListenerTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
