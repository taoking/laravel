<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $topic['title'] }} - PHP 常用函数练习实验室</title>
    <style>
        body { color: #202124; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; background: #f5f7fb; }
        main { max-width: 1180px; margin: 0 auto; padding: 32px 20px 48px; }
        a { color: #0b57d0; text-decoration: none; }
        a:hover { text-decoration: underline; }
        h1 { font-size: 28px; margin: 0 0 8px; }
        h2 { font-size: 18px; margin: 26px 0 12px; }
        p, li { color: #4b5563; line-height: 1.7; }
        ul { margin: 0; padding-left: 20px; }
        pre { background: #111827; border-radius: 6px; color: #e5e7eb; font-size: 14px; line-height: 1.6; overflow-x: auto; padding: 16px; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 10px; margin: 18px 0 26px; }
        .toolbar a { background: #fff; border: 1px solid #d9dee7; border-radius: 6px; padding: 8px 12px; }
        .panel { background: #fff; border: 1px solid #d9dee7; border-radius: 8px; margin-top: 16px; padding: 18px; }
        .badge { background: #e7f0ff; border-radius: 999px; color: #174ea6; display: inline-block; font-size: 12px; padding: 3px 8px; }
        .output { background: #f8fafc; border: 1px solid #e6e9ef; border-radius: 6px; padding: 12px 14px; }
        .output li { color: #1f2937; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }
    </style>
</head>
<body>
<main>
    <a href="{{ route('php-lab.index') }}">返回 PHP Lab</a>
    <h1>{{ $topic['title'] }}</h1>
    <p><span class="badge">{{ $topic['level'] }}</span></p>
    <p>{{ $topic['summary'] }}</p>

    <nav class="toolbar" aria-label="PHP Lab 主题切换">
        @foreach ($topics as $item)
            <a href="{{ route('php-lab.show', ['topic' => $item['key']]) }}">
                {{ $item['title'] }}
            </a>
        @endforeach
    </nav>

    <section class="panel">
        <h2>常用函数</h2>
        <ul>
            @foreach ($topic['methods'] as $method)
                <li><code>{{ $method }}</code></li>
            @endforeach
        </ul>
    </section>

    <section class="panel">
        <h2>使用要点</h2>
        <ul>
            @foreach ($topic['concepts'] as $concept)
                <li>{{ $concept }}</li>
            @endforeach
        </ul>
    </section>

    <section class="panel">
        <h2>示例代码</h2>
        <pre><code>{{ $topic['code'] }}</code></pre>
    </section>

    <section class="panel">
        <h2>运行结果</h2>
        <ul class="output">
            @foreach ($topic['output'] as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    </section>

    <section class="panel">
        <h2>练习任务</h2>
        <ul>
            @foreach ($topic['practice'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </section>
</main>
</body>
</html>
