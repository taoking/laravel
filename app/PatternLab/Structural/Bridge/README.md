# Bridge 桥接模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成桥接模式代码实现。

## 练习内容

**具体功能：消息类型与发送渠道组合**

你正在实现消息系统，验证码、营销消息、系统告警三类内容都可能通过邮件、短信、Webhook 或站内信发送。

具体要求：

- 把消息内容类型和发送渠道拆成两个独立变化维度。
- 避免创建 `SmsAlertMessage`、`EmailAlertMessage` 这类组合爆炸类。
- 在 Exercise.php::run() 中演示“系统告警消息 + Webhook 渠道”的组合。
- 补充测试验证新增渠道时不需要修改已有消息类型。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- Message.php
- VerificationMessage.php
- AlertMessage.php
- MessageChannelInterface.php
- EmailChannel.php
- SmsChannel.php

## 运行入口

```bash
php artisan pattern-lab:show bridge
```

## 测试入口

```bash
php artisan test --filter BridgeTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
