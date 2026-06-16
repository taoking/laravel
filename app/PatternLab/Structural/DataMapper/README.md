# Data Mapper 数据映射模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成数据映射模式代码实现。

## 练习内容

**具体功能：用户资料领域对象持久化**

你正在实现用户资料模块，领域对象只表达业务属性和行为，数据库读写由 Mapper 负责。

具体要求：

- 设计不依赖 Eloquent 的用户资料领域对象。
- 设计 Mapper 负责把数据库记录转换成领域对象，并把领域对象保存回记录。
- 在 Exercise.php::run() 中演示加载用户资料、修改昵称、准备保存的过程。
- 补充测试验证领域对象不直接调用数据库或 ORM。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- UserProfile.php
- UserProfileMapper.php
- UserProfileRecord.php

## 运行入口

```bash
php artisan pattern-lab:show data-mapper
```

## 测试入口

```bash
php artisan test --filter DataMapperTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
