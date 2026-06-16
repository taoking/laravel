# Facade 外观模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成外观模式代码实现。

## 练习内容

**具体功能：订单创建流程统一入口**

你正在实现下单流程，创建订单时需要协调库存、支付单、通知和操作日志多个子系统。

具体要求：

- 设计一个高层业务入口，隐藏内部多个服务的调用顺序。
- 让 Controller 或 Exercise 只调用一个订单创建入口。
- 在 Exercise.php::run() 中演示创建一笔包含两个商品的订单流程摘要。
- 补充测试验证入口输出包含订单、库存、支付单和通知步骤。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- OrderCreationFacade.php
- StockService.php
- PaymentOrderService.php
- NotificationService.php
- OperationLogService.php

## 运行入口

```bash
php artisan pattern-lab:show facade
```

## 测试入口

```bash
php artisan test --filter FacadeTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
