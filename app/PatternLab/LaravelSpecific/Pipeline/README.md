# Pipeline 管道模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成管道模式代码实现。

## 练习内容

**具体功能：导入数据清洗管道**

你正在实现 CSV 导入前的数据处理，原始行需要依次经过去空格、字段校验、格式标准化、业务补全和持久化准备。

具体要求：

- 设计多个 Pipe，每个 Pipe 只处理一件事并把数据交给下一步。
- 明确每一步输入输出的数据结构。
- 在 Exercise.php::run() 中演示一行原始数据通过清洗管道得到规范化结果。
- 补充测试验证步骤顺序、失败中断和单个 Pipe 可独立测试。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- ImportPipeline.php
- TrimRowPipe.php
- ValidateRowPipe.php
- NormalizeRowPipe.php
- PersistRowPipe.php

## 运行入口

```bash
php artisan pattern-lab:show pipeline
```

## 测试入口

```bash
php artisan test --filter PipelineTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
