<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PatternLab 设计模式练习</title>
    <style>
        body { color: #202124; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; background: #f7f8fa; }
        main { max-width: 1180px; margin: 0 auto; padding: 32px 20px 48px; }
        a { color: #0b57d0; text-decoration: none; }
        a:hover { text-decoration: underline; }
        h1 { font-size: 28px; margin: 0 0 8px; }
        h2 { font-size: 18px; margin: 28px 0 12px; }
        p { color: #4b5563; line-height: 1.7; margin: 0 0 16px; }
        nav { display: flex; flex-wrap: wrap; gap: 10px; margin: 18px 0 26px; }
        nav a { background: #fff; border: 1px solid #d9dee7; border-radius: 6px; padding: 8px 12px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #d9dee7; }
        th, td { border-bottom: 1px solid #e6e9ef; padding: 10px 12px; text-align: left; vertical-align: top; }
        th { background: #eef2f7; color: #374151; font-size: 13px; }
        td { font-size: 14px; line-height: 1.55; }
        .muted { color: #667085; }
        .status { color: #92400e; font-weight: 600; }
    </style>
</head>
<body>
<main>
    <h1>PatternLab 设计模式练习</h1>
    <p>这里只展示设计模式说明、场景、路径和 TODO 骨架，不执行具体设计模式实现。</p>

    <nav aria-label="PatternLab 分类">
        @foreach ($categories as $category)
            <a href="{{ route('pattern-lab.category', ['category' => $category['key']]) }}">
                {{ $category['name_cn'] }} / {{ $category['name'] }}
            </a>
        @endforeach
    </nav>

    <h2>全部模式</h2>
    <table>
        <thead>
        <tr>
            <th>分类</th>
            <th>英文名</th>
            <th>中文名</th>
            <th>难度</th>
            <th>状态</th>
            <th>使用场景</th>
            <th>常见使用地方</th>
            <th>详情</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($patterns as $pattern)
            <tr>
                <td>
                    <a href="{{ route('pattern-lab.category', ['category' => $pattern['category']]) }}">
                        {{ $pattern['category_cn'] }}
                    </a>
                </td>
                <td>{{ $pattern['name'] }}</td>
                <td>{{ $pattern['name_cn'] }}</td>
                <td>{{ $pattern['difficulty'] }}</td>
                <td class="status">{{ $pattern['status'] }}</td>
                <td>{{ $pattern['scenario'] }}</td>
                <td class="muted">{{ implode('、', $pattern['common_usage']) }}</td>
                <td>
                    <a href="{{ route('pattern-lab.show', ['category' => $pattern['category'], 'pattern' => $pattern['key']]) }}">
                        查看
                    </a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</main>
</body>
</html>
