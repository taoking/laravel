<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pattern['name'] }} - PatternLab</title>
    <style>
        body { color: #202124; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; background: #f7f8fa; }
        main { max-width: 980px; margin: 0 auto; padding: 32px 20px 48px; }
        a { color: #0b57d0; text-decoration: none; }
        a:hover { text-decoration: underline; }
        h1 { font-size: 28px; margin: 0 0 6px; }
        h2 { font-size: 18px; margin: 26px 0 10px; }
        p, li { color: #374151; line-height: 1.75; }
        code { background: #eef2f7; border-radius: 4px; padding: 2px 5px; }
        .back { display: inline-block; margin-bottom: 18px; }
        .meta { background: #fff; border: 1px solid #d9dee7; border-radius: 6px; display: grid; gap: 0; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 18px 0 24px; }
        .meta div { border-bottom: 1px solid #e6e9ef; padding: 10px 12px; }
        .meta div:nth-child(odd) { border-right: 1px solid #e6e9ef; }
        .label { color: #667085; display: block; font-size: 13px; margin-bottom: 4px; }
        .path-list { background: #fff; border: 1px solid #d9dee7; border-radius: 6px; padding: 12px 16px; }
        @media (max-width: 720px) {
            .meta { grid-template-columns: 1fr; }
            .meta div:nth-child(odd) { border-right: 0; }
        }
    </style>
</head>
<body>
<main>
    <a class="back" href="{{ route('pattern-lab.category', ['category' => $pattern['category']]) }}">返回{{ $pattern['category_cn'] }}</a>
    <h1>{{ $pattern['name'] }} / {{ $pattern['name_cn'] }}</h1>
    <p>{{ $pattern['summary'] }}</p>

    <section class="meta" aria-label="模式元信息">
        <div><span class="label">分类</span>{{ $pattern['category_name'] }} / {{ $pattern['category_cn'] }}</div>
        <div><span class="label">难度</span>{{ $pattern['difficulty'] }}</div>
        <div><span class="label">状态</span>{{ $pattern['status'] }}</div>
        <div><span class="label">Key</span>{{ $pattern['key'] }}</div>
    </section>

    <h2>解决什么问题</h2>
    <p>{{ $pattern['problem'] }}</p>

    <h2>使用场景</h2>
    <p>{{ $pattern['scenario'] }}</p>

    <h2>常见使用地方</h2>
    <ul>
        @foreach ($pattern['common_usage'] as $usage)
            <li>{{ $usage }}</li>
        @endforeach
    </ul>

    <h2>不使用模式可能的问题</h2>
    <ul>
        <li>业务分支或流程步骤堆积在一个类中。</li>
        <li>新增变化点时修改范围扩大，回归风险升高。</li>
        <li>测试难以只覆盖一个具体规则或协作对象。</li>
    </ul>

    <h2>练习目标</h2>
    <ul>
        @foreach ($pattern['exercise_goals'] as $goal)
            <li>{{ $goal }}</li>
        @endforeach
    </ul>

    <h2>练习内容</h2>
    <p><strong>具体功能：{{ $pattern['exercise_content']['title'] }}</strong></p>
    <p>{{ $pattern['exercise_content']['description'] }}</p>
    <ul>
        @foreach ($pattern['exercise_content']['requirements'] as $requirement)
            <li>{{ $requirement }}</li>
        @endforeach
    </ul>

    <h2>建议创建的类</h2>
    <ul>
        @foreach ($pattern['suggested_classes'] as $class)
            <li><code>{{ $class }}</code></li>
        @endforeach
    </ul>

    <h2>路径</h2>
    <div class="path-list">
        <p>文档：<code>{{ $pattern['doc_path'] }}</code></p>
        <p>代码目录：<code>{{ $pattern['exercise_path'] }}</code></p>
        <p>测试文件：<code>{{ $pattern['test_path'] }}</code></p>
    </div>

    <h2>TODO 清单</h2>
    <ul>
        <li>阅读模式说明文档。</li>
        <li>理解使用场景和常见使用地方。</li>
        <li>自己设计接口、具体类和调用入口。</li>
        <li>完成 <code>Exercise.php</code> 后补充测试。</li>
        <li>练习完成后把 <code>PatternRegistry</code> 中状态改为 <code>done</code>。</li>
    </ul>
</main>
</body>
</html>
