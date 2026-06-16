# Job / Command 任务命令模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成任务命令模式代码实现。

## 练习内容

**具体功能：异步报表导出 Job**

你正在实现报表导出任务，请求只创建导出任务记录，真正的文件生成交给 Job 异步执行。

具体要求：

- 设计 Job 输入，只传递导出任务 id 或必要的轻量参数。
- Job 内部负责加载查询条件、生成文件、更新进度和记录失败原因。
- 在 Exercise.php::run() 中演示同步执行一个模拟导出 Job 的流程摘要。
- 补充测试验证任务可重试、失败状态可记录，并且请求流程不阻塞等待文件生成。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- ExportReportJob.php
- SendMailJob.php
- GenerateFileJob.php

## 运行入口

```bash
php artisan pattern-lab:show job-command
```

## 测试入口

```bash
php artisan test --filter JobCommandTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
