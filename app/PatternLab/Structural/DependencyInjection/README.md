# Dependency Injection 依赖注入练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成依赖注入代码实现。

## 练习内容

**具体功能：订单服务依赖替换**

你正在实现订单提交服务，它依赖支付网关、库存服务和通知服务，但不应在类内部直接创建这些依赖。

具体要求：

- 定义订单服务需要的依赖接口，并通过构造函数注入。
- 准备真实实现和测试替身的设计位置。
- 在 Exercise.php::run() 中用模拟依赖演示提交订单流程。
- 补充测试验证替换支付网关实现时不需要修改订单服务。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- OrderService.php
- PaymentServiceInterface.php
- StockServiceInterface.php
- NotificationServiceInterface.php

## 运行入口

```bash
php artisan pattern-lab:show dependency-injection
```

## 测试入口

```bash
php artisan test --filter DependencyInjectionTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
