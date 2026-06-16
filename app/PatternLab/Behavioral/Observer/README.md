# Observer 观察者模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成观察者模式代码实现。

## 练习内容

**具体功能：订单支付成功后续动作**

你正在实现订单支付成功后的扩展点，支付完成后需要发送通知、增加积分、写日志和推送统计数据。

具体要求：

- 定义支付成功事件或主题对象，并允许多个观察者订阅。
- 每个观察者只处理一个后续动作，不要把所有动作写进支付主流程。
- 在 Exercise.php::run() 中演示发布一次订单支付成功事件后触发多个观察者。
- 补充测试验证新增观察者不需要修改事件发布方。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- OrderPaidEvent.php
- SendPaymentNotificationListener.php
- AddUserPointListener.php
- WriteOrderLogListener.php

## 运行入口

```bash
php artisan pattern-lab:show observer
```

## 测试入口

```bash
php artisan test --filter ObserverTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
