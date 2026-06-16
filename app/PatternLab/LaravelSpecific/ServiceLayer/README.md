# Service Layer 服务层模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成服务层模式代码实现。

## 练习内容

**具体功能：订单创建应用服务**

你正在实现下单业务，Controller 只负责接收请求，订单创建、库存扣减、支付单创建和通知由 Service 编排。

具体要求：

- 设计一个订单创建服务，表达完整业务流程。
- 让 Controller 或 Exercise 只传入已验证的数据并接收业务结果。
- 在 Exercise.php::run() 中演示创建订单服务返回订单编号和下一步支付信息。
- 补充测试验证 Controller 不包含核心业务分支。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- OrderApplicationService.php
- PaymentConfirmationService.php
- ReportGenerationService.php

## 运行入口

```bash
php artisan pattern-lab:show service-layer
```

## 测试入口

```bash
php artisan test --filter ServiceLayerTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
