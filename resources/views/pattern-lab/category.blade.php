<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $category['name_cn'] }} - PatternLab</title>
    <style>
        body { color: #202124; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; background: #f7f8fa; }
        main { max-width: 1080px; margin: 0 auto; padding: 32px 20px 48px; }
        a { color: #0b57d0; text-decoration: none; }
        a:hover { text-decoration: underline; }
        h1 { font-size: 26px; margin: 0 0 8px; }
        p { color: #4b5563; line-height: 1.7; margin: 0 0 18px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #d9dee7; }
        th, td { border-bottom: 1px solid #e6e9ef; padding: 10px 12px; text-align: left; vertical-align: top; }
        th { background: #eef2f7; color: #374151; font-size: 13px; }
        td { font-size: 14px; line-height: 1.55; }
        .back { display: inline-block; margin-bottom: 18px; }
        .status { color: #92400e; font-weight: 600; }
    </style>
</head>
<body>
<main>
    <a class="back" href="{{ route('pattern-lab.index') }}">返回全部模式</a>
    <h1>{{ $category['name_cn'] }} / {{ $category['name'] }}</h1>
    <p>{{ $category['description'] }}</p>

    <table>
        <thead>
        <tr>
            <th>英文名</th>
            <th>中文名</th>
            <th>难度</th>
            <th>状态</th>
            <th>一句话说明</th>
            <th>使用场景</th>
            <th>详情</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($patterns as $pattern)
            <tr>
                <td>{{ $pattern['name'] }}</td>
                <td>{{ $pattern['name_cn'] }}</td>
                <td>{{ $pattern['difficulty'] }}</td>
                <td class="status">{{ $pattern['status'] }}</td>
                <td>{{ $pattern['summary'] }}</td>
                <td>{{ $pattern['scenario'] }}</td>
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
