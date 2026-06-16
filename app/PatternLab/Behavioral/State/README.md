# State 状态模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成状态模式代码实现。

## 练习内容

**具体功能：订单状态流转器**

你正在实现订单状态流转，不同状态下可执行的动作不同，例如待支付、已支付、已发货、已完成和已取消。

具体要求：

- 定义状态契约，把每个状态允许的行为放到对应状态类。
- 明确哪些状态可以支付、发货、完成或取消。
- 在 Exercise.php::run() 中演示订单从待支付到已支付再到已发货的流转摘要。
- 补充测试验证非法流转会被拒绝，而不是静默成功。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- OrderStateInterface.php
- PendingPaymentState.php
- PaidState.php
- ShippedState.php
- CancelledState.php
- OrderContext.php

## 运行入口

```bash
php artisan pattern-lab:show state
```

## 测试入口

```bash
php artisan test --filter StateTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
