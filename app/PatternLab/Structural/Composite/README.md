# Composite 组合模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成组合模式代码实现。

## 练习内容

**具体功能：后台菜单和权限树**

你正在实现后台导航权限树，菜单组、菜单项和按钮权限需要被统一遍历和过滤。

具体要求：

- 定义统一节点契约，让叶子节点和组合节点能被一致处理。
- 实现菜单组包含子节点，菜单项或按钮作为叶子节点。
- 在 Exercise.php::run() 中构建一个“系统管理 > 用户管理 > 新增按钮”的小树。
- 补充测试验证树遍历、层级路径和按权限过滤后的结果。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- MenuNodeInterface.php
- MenuItem.php
- MenuGroup.php
- PermissionNode.php

## 运行入口

```bash
php artisan pattern-lab:show composite
```

## 测试入口

```bash
php artisan test --filter CompositeTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
