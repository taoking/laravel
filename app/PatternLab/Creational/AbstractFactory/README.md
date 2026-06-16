# Abstract Factory 抽象工厂练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成抽象工厂代码实现。

## 练习内容

**具体功能：支付渠道组件族**

你正在实现一个支付接入层，支付宝和微信支付都需要成套创建支付请求构造器、回调解析器和退款处理器。

具体要求：

- 定义支付组件族契约，确保同一渠道的请求、回调、退款组件来自同一个工厂。
- 至少设计 `alipay` 和 `wechat` 两组具体工厂。
- 在 Exercise.php::run() 中演示选择 `wechat` 工厂并列出本次创建的组件。
- 补充测试验证不会混用不同支付渠道的组件族。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- PaymentComponentFactoryInterface.php
- PaymentRequestBuilderInterface.php
- PaymentCallbackParserInterface.php
- RefundProcessorInterface.php
- AlipayPaymentFactory.php
- WechatPaymentFactory.php

## 运行入口

```bash
php artisan pattern-lab:show abstract-factory
```

## 测试入口

```bash
php artisan test --filter AbstractFactoryTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
