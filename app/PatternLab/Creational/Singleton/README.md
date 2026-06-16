# Singleton 单例模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成单例模式代码实现。

## 练习内容

**具体功能：应用运行参数读取器**

你正在实现一个运行参数读取器，用来读取当前练习环境配置，并观察同一请求内共享实例带来的便利和风险。

具体要求：

- 设计一个最小配置读取入口，能读取 app name、timezone 或自定义练习参数。
- 实现时记录为什么手写单例会带来全局状态和测试隔离问题。
- 在 Exercise.php::run() 中演示两次读取同一配置，并说明它们是否来自同一实例。
- 补充测试时优先思考如何重置状态，以及 Laravel 容器 singleton 是否更合适。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- ConfigReader.php
- ConfigReaderSingleton.php

## 运行入口

```bash
php artisan pattern-lab:show singleton
```

## 测试入口

```bash
php artisan test --filter SingletonTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
