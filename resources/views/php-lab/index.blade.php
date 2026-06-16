<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHP 基础练习实验室</title>
    <style>
        body { color: #202124; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; background: #f5f7fb; }
        main { max-width: 1180px; margin: 0 auto; padding: 32px 20px 48px; }
        a { color: #0b57d0; text-decoration: none; }
        a:hover { text-decoration: underline; }
        h1 { font-size: 28px; margin: 0 0 8px; }
        h2 { font-size: 18px; margin: 28px 0 12px; }
        p { color: #4b5563; line-height: 1.7; margin: 0 0 16px; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 10px; margin: 18px 0 26px; }
        .toolbar a { background: #fff; border: 1px solid #d9dee7; border-radius: 6px; padding: 8px 12px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #d9dee7; }
        th, td { border-bottom: 1px solid #e6e9ef; padding: 10px 12px; text-align: left; vertical-align: top; }
        th { background: #eef2f7; color: #374151; font-size: 13px; }
        td { font-size: 14px; line-height: 1.55; }
        .badge { background: #e7f0ff; border-radius: 999px; color: #174ea6; display: inline-block; font-size: 12px; padding: 3px 8px; }
        .muted { color: #667085; }
    </style>
</head>
<body>
<main>
    <h1>PHP 基础练习实验室</h1>
    <p>面向 PHP 基础语法、常用函数和运行时特性的练习模块。每个主题包含知识点、示例代码、运行结果和动手任务。</p>

    <nav class="toolbar" aria-label="PHP Lab 快速入口">
        @foreach ($topics as $topic)
            <a href="{{ route('php-lab.show', ['topic' => $topic['key']]) }}">
                {{ $topic['title'] }}
            </a>
        @endforeach
    </nav>

    <h2>练习主题</h2>
    <table>
        <thead>
        <tr>
            <th>主题</th>
            <th>阶段</th>
            <th>练习内容</th>
            <th>核心知识点</th>
            <th>详情</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($topics as $topic)
            <tr>
                <td>{{ $topic['title'] }}</td>
                <td><span class="badge">{{ $topic['level'] }}</span></td>
                <td>{{ $topic['summary'] }}</td>
                <td class="muted">{{ implode('、', array_slice($topic['concepts'], 0, 2)) }}</td>
                <td>
                    <a href="{{ route('php-lab.show', ['topic' => $topic['key']]) }}">
                        开始练习
                    </a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</main>
</body>
</html>
