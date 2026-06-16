# Command 命令模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成命令模式代码实现。

## 练习内容

**具体功能：后台任务命令对象**

你正在实现后台批处理中心，把导出报表、发送通知、清理缓存等操作封装为可执行命令对象。

具体要求：

- 定义命令对象契约，表达 `execute()` 和必要的任务描述。
- 设计一个命令执行入口，可以记录执行结果或失败原因。
- 在 Exercise.php::run() 中演示执行“导出日报表”命令。
- 补充测试验证命令可以被排队、记录和替换执行器。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- TaskCommandInterface.php
- ExportReportCommand.php
- SendNotificationCommand.php
- CommandBus.php

## 运行入口

```bash
php artisan pattern-lab:show command
```

## 测试入口

```bash
php artisan test --filter CommandTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
