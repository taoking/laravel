# Factory Method 工厂方法练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成工厂方法代码实现。

## 练习内容

**具体功能：多渠道通知发送器创建**

你正在实现一个通知中心，不同通知渠道由不同工厂创建发送器，用于发送订单状态变更提醒。

具体要求：

- 定义通知发送器契约，至少考虑 email、sms、webhook、in-app 四种渠道。
- 定义抽象工厂入口，让每个具体工厂负责创建自己渠道的发送器。
- 在 Exercise.php::run() 中演示创建短信发送器并生成一条订单发货通知。
- 补充测试对比不同工厂创建出的发送器类型和输出内容。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- NotificationSenderInterface.php
- NotificationFactory.php
- EmailNotificationFactory.php
- SmsNotificationFactory.php
- WebhookNotificationFactory.php

## 运行入口

```bash
php artisan pattern-lab:show factory-method
```

## 测试入口

```bash
php artisan test --filter FactoryMethodTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
