<?php

/**
 * @var \Leaf\Crash\Report $report
 * @var \Leaf\Crash\Frame[] $frames
 * @var int $activeIndex
 * @var string $prompt
 * @var string $markdown
 * @var array $options
 * @var callable $e
 */

$groups = \Leaf\Crash\Renderer\HtmlRenderer::groupFrames($frames);

$framesJson = json_encode(array_map(fn ($f) => [
    'file' => $f->file,
    'line' => $f->line,
    'callable' => $f->callable(),
    'isApplication' => $f->isApplication,
    'excerpt' => (object) $f->excerpt,
], $frames), JSON_PARTIAL_OUTPUT_ON_ERROR);

$editorSchemes = [
    'vscode' => 'vscode://file/%s:%d',
    'cursor' => 'cursor://file/%s:%d',
    'phpstorm' => 'phpstorm://open?file=%s&line=%d',
    'sublime' => 'subl://open?url=file://%s&line=%d',
    'zed' => 'zed://file/%s:%d',
];
$editorScheme = $editorSchemes[$options['editor'] ?? 'vscode'] ?? $editorSchemes['vscode'];
$editorUrl = fn ($file, $line) => sprintf($editorScheme, $file, (int) $line);

$curl = null;

if (!empty($report->request['method']) && !empty($report->request['url'])) {
    $parts = ['curl -X ' . escapeshellarg($report->request['method'])];

    foreach (($report->request['headers'] ?? []) as $header => $headerValue) {
        if (is_scalar($headerValue)) {
            $parts[] = '-H ' . escapeshellarg("$header: $headerValue");
        }
    }

    if (!empty($report->request['body']) && is_array($report->request['body'])) {
        $parts[] = "-H 'Content-Type: application/json'";
        $parts[] = '-d ' . escapeshellarg(json_encode($report->request['body']));
    }

    $parts[] = escapeshellarg($report->request['url']);
    $curl = implode(" \\\n  ", $parts);
}

$export = $report->toArray();

if (!empty($options['projectContext'])) {
    $export['ai'] = [
        'projectContext' => $options['projectContext'],
        'instructions' => 'Use the project context, the user journey (breadcrumbs), the peeked values and the crash frames to find the likely root cause and suggest a concrete fix.',
    ];
}

/**
 * Render exported data as a collapsible json tree, devtools style.
 * Cutoff markers from Peek ("[array:2]", "(truncated)", ...) show muted.
 */
$tree = function ($value, int $depth = 0) use (&$tree, $e): string {
    if (is_array($value)) {
        $isList = array_is_list($value);
        $badge = $isList ? '[' . count($value) . ']' : '{' . count($value) . '}';
        $rows = '';

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $child = $tree($item, $depth + 1);
                $childBadge = array_is_list($item) ? '[' . count($item) . ']' : '{' . count($item) . '}';
                $rows .= '<details class="jt"' . ($depth < 1 ? ' open' : '') . '><summary><span class="jt-k">' . $e($key) . '</span> <span class="jt-b">' . $childBadge . '</span></summary>' . $child . '</details>';
            } else {
                $rows .= '<div class="jt-row"><span class="jt-k">' . $e($key) . '</span> : ' . $tree($item, $depth + 1) . '</div>';
            }
        }

        return $depth === 0
            ? '<div class="jt-root"><div class="jt-row"><span class="jt-b">' . $badge . '</span></div>' . $rows . '</div>'
            : $rows;
    }

    if (is_string($value)) {
        if (preg_match('/^(\[array:\d+\]|\(object [^)]+\)|\(closure\)|\(truncated\)|\(resource:[^)]+\)|\(\d+ more\)|\(more\))$/', $value)) {
            return '<span class="jt-cut">' . $e($value) . '</span>';
        }

        return '<span class="jt-s">"' . $e($value) . '"</span>';
    }

    if (is_bool($value)) {
        return '<span class="jt-x">' . ($value ? 'true' : 'false') . '</span>';
    }

    if ($value === null) {
        return '<span class="jt-x">null</span>';
    }

    return '<span class="jt-n">' . $e($value) . '</span>';
};

$levelColors = ['error' => 'var(--red)', 'warning' => 'var(--amber)', 'info' => 'var(--blue)', 'debug' => 'var(--muted)'];
$levelColor = $levelColors[$report->level] ?? 'var(--red)';
$sectionNumber = 0;

$logo = @file_get_contents(__DIR__ . '/../assets/logo-circle.png');
$logo = $logo ? 'data:image/png;base64,' . base64_encode($logo) : null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($report->exception) ?>: <?= $e(mb_substr($report->message, 0, 80)) ?></title>
<style>
    :root {
        /* leaf v5 — warm paper, editorial */
        --bg: #FAF7F2; --panel: #FFFFFF; --panel-2: #F6F1EA; --line: #E8E0D8; --line-soft: #F0E9E1;
        --text: #262019; --muted: #6B5B4E; --faint: #A09080;
        --brand: #D4652E; --brand-hover: #BE4A1F; --gold: #F5B731;
        --red: #C73E2E; --red-soft: #FBEAE6; --amber: #A8720E; --amber-soft: #F8EDD4; --blue: #4c7fd8; --green: #3C8A5B;
        --code-bg: #1E1714; --code-text: #EBE1D7; --code-num: #6B5B4E; --code-hit: #4A2013; --code-hit-text: #FFB899;
       
    }
    /* follows the system theme; an explicit data-theme (set by the toggle) wins */
    @media (prefers-color-scheme: dark) {
        body:not([data-theme="light"]) {
        --bg: #0A0807; --panel: #110E0B; --panel-2: #1A1512; --line: #2D2720; --line-soft: #221D17;
        --text: #EBE1D7; --muted: #A89080; --faint: #6B5B4E;
        --brand: #E8873A; --brand-hover: #F5B731; --gold: #F5B731;
        --red: #FF7A5C; --red-soft: #331410; --amber: #F5B731; --amber-soft: #2A2008; --blue: #89ddff; --green: #7CC79A;
        --code-bg: #0A0807; --code-text: #DCD2C6; --code-num: #4A3E33; --code-hit: #47191d; --code-hit-text: #FFB899;
        }
    }
    body[data-theme="dark"] {
        --bg: #0A0807; --panel: #110E0B; --panel-2: #1A1512; --line: #2D2720; --line-soft: #221D17;
        --text: #EBE1D7; --muted: #A89080; --faint: #6B5B4E;
        --brand: #E8873A; --brand-hover: #F5B731; --gold: #F5B731;
        --red: #FF7A5C; --red-soft: #331410; --amber: #F5B731; --amber-soft: #2A2008; --blue: #89ddff; --green: #7CC79A;
        --code-bg: #0A0807; --code-text: #DCD2C6; --code-num: #4A3E33; --code-hit: #47191d; --code-hit-text: #FFB899;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0; background: var(--bg); color: var(--text);
        font: 14px/1.65 Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        -webkit-font-smoothing: antialiased;
    }
    .mono { font-family: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, monospace; }
    .display { font-family: "Bricolage Grotesque", Inter, ui-sans-serif, sans-serif; }
    .wrap { max-width: 1160px; margin: 0 auto; padding: 0 28px 90px; }

    /* eyebrows: mono, tracked caps, comment prefix */
    .eyebrow { font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); }
    .eyebrow::before { content: "// "; color: var(--brand); }

    /* top bar */
    .bar { display: flex; align-items: center; gap: 14px; padding: 16px 0; border-bottom: 1px solid var(--line); margin-bottom: 44px; }
    .brand { display: flex; align-items: center; gap: 9px; font-weight: 700; font-size: 15px; letter-spacing: -.02em; }
    .brand img.leaf { width: 24px; height: 24px; border-radius: 50%; display: block; }
    .brand span.leaf { width: 22px; height: 22px; border-radius: 50%; background: #262019; position: relative; }
    .brand span.leaf::after { content: ""; position: absolute; inset: 6px 6px 6px 10px; border-radius: 0 8px 0 8px; background: var(--gold); }
    .bar .spacer { flex: 1; }
    .btn {
        font-size: 13px; padding: 7px 14px; border-radius: 7px; border: 1px solid var(--line);
        background: var(--panel); color: var(--text); cursor: pointer; text-decoration: none; display: inline-flex; gap: 7px; align-items: center;
    }
    .btn:hover { border-color: var(--brand-hover); color: var(--brand-hover); }
    .btn.tinted {
        font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 12px;
        border-color: color-mix(in srgb, var(--brand) 38%, transparent);
        background: color-mix(in srgb, var(--brand) 9%, transparent);
        color: var(--brand);
    }
    .btn.tinted:hover { border-color: var(--brand); background: color-mix(in srgb, var(--brand) 16%, transparent); color: var(--brand); }
    .ai-menu { position: relative; }
    .ai-menu .caret { font-size: 10px; transform: rotate(180deg); transition: transform .15s; display: inline-block; }
    .ai-menu.open .caret { transform: rotate(0); }
    .ai-panel {
        display: none; position: absolute; right: 0; top: calc(100% + 8px); z-index: 20; min-width: 270px;
        background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 12px 10px;
        box-shadow: 0 12px 32px -12px rgba(38, 24, 12, .25);
    }
    .ai-menu.open .ai-panel { display: block; }
    .ai-label { font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 10.5px; letter-spacing: .14em; text-transform: uppercase; color: var(--faint); padding: 4px 10px 8px; }
    .ai-item {
        display: flex; align-items: center; gap: 12px; width: 100%; text-align: left; padding: 8px 10px; border: 0;
        border-radius: 8px; background: none; color: var(--text); font: 500 13.5px/1.4 Inter, ui-sans-serif, sans-serif;
        cursor: pointer; text-decoration: none;
    }
    .ai-item:hover { background: var(--panel-2); color: var(--text); }
    .ai-ico {
        width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; flex: none;
        border: 1px solid var(--line); border-radius: 7px; font-family: "JetBrains Mono", ui-monospace, monospace;
        font-size: 11px; font-weight: 700; color: var(--brand);
    }
    .ai-item .ext { margin-left: auto; color: var(--faint); }

    /* hero: editorial headline, no card */
    .hero { position: relative; padding: 4px 2px 40px; }
    .hero .pill {
        display: inline-flex; align-items: center; gap: 8px; font-size: 11.5px; letter-spacing: .08em; text-transform: uppercase;
        font-family: "JetBrains Mono", ui-monospace, monospace; color: var(--muted);
        border: 1px solid var(--line); border-radius: 99px; padding: 5px 14px; background: var(--panel);
    }
    .hero .pill .dot { width: 7px; height: 7px; border-radius: 99px; background: <?= $levelColor ?>; }
    .hero h1 { font-size: clamp(28px, 4.4vw, 44px); line-height: 1.12; letter-spacing: -.03em; font-weight: 700; margin: 22px 0 20px; max-width: 24ch; }
    .facts { display: grid; gap: 7px; max-width: 760px; }
    .fact { display: flex; align-items: baseline; gap: 12px; font-size: 12.5px; }
    .fact .k { color: var(--faint); text-transform: uppercase; letter-spacing: .1em; font-size: 10.5px; min-width: 92px; }
    .fact .leader { flex: 1; border-bottom: 1px dotted var(--line); transform: translateY(-4px); }
    .fact .v { color: var(--muted); overflow-wrap: anywhere; text-align: right; }
    .fact .v.hl { color: var(--text); }

    /* solution: hairline frame with corner ticks */
    .solution { position: relative; border: 1px solid var(--line); background: var(--panel); padding: 18px 22px; margin: 0 0 44px; display: flex; gap: 12px; align-items: baseline; }
    .solution .spark { color: var(--brand); }
    .tick { position: absolute; width: 7px; height: 7px; }
    .tick::before, .tick::after { content: ""; position: absolute; background: var(--brand); }
    .tick::before { width: 7px; height: 1.5px; } .tick::after { width: 1.5px; height: 7px; }
    .tick.tl { top: -1px; left: -1px; } .tick.tr { top: -1px; right: -1px; transform: scaleX(-1); }
    .tick.bl { bottom: -1px; left: -1px; transform: scaleY(-1); } .tick.br { bottom: -1px; right: -1px; transform: scale(-1); }

    /* sections: flat, hairline-framed, numbered mono headers */
    .section { border: 1px solid var(--line); background: var(--panel); margin-bottom: 44px; position: relative; }
    .sechead { display: flex; align-items: center; padding: 13px 20px; border-bottom: 1px solid var(--line); }
    .sechead .index { font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 11px; color: var(--faint); letter-spacing: .1em; }
    .sechead .index b { color: var(--text); font-weight: 500; }
    .sechead .rule { width: 26px; height: 2px; background: var(--brand); margin-left: 14px; }
    .sechead .label { font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); }

    /* stack layout: shared hairline grid, not floating cards */
    .stackgrid { display: grid; grid-template-columns: 330px 1fr; }
    .stackgrid > * { min-width: 0; }
    @media (max-width: 900px) { .stackgrid { grid-template-columns: 1fr; } }
    .frame-list { border-right: 1px solid var(--line); max-height: 620px; overflow-y: auto; }
    @media (max-width: 900px) { .frame-list { border-right: 0; border-bottom: 1px solid var(--line); max-height: 300px; } }
    .frame { padding: 11px 20px; cursor: pointer; border-left: 2px solid transparent; border-bottom: 1px solid var(--line-soft); }
    .frame:hover { background: var(--panel-2); }
    .frame.active { background: var(--red-soft); border-left-color: var(--red); }
    .frame .fn { font-size: 12.5px; font-weight: 600; overflow-wrap: anywhere; }
    .frame .loc { font-size: 11.5px; color: var(--muted); overflow-wrap: anywhere; }
    .frame .ed { float: right; color: var(--faint); text-decoration: none; font-size: 11px; padding: 0 2px; }
    .frame .ed:hover { color: var(--brand); }
    .vendor-toggle { padding: 10px 20px; font-size: 11.5px; letter-spacing: .06em; color: var(--faint); cursor: pointer; user-select: none; border-bottom: 1px solid var(--line-soft); text-transform: uppercase; }
    .vendor-toggle:hover { color: var(--muted); }
    .vendor-group.collapsed .frame { display: none; }
    .vendor-group .frame .fn { font-weight: 400; color: var(--muted); }

    .code { background: var(--code-bg); color: var(--code-text); overflow-x: auto; padding: 14px 0; min-height: 340px; }
    .code .row { white-space: pre; padding: 0 20px; line-height: 1.75; font-size: 13px; }
    .code .row.hit { background: var(--code-hit); color: var(--code-hit-text); }
    .code .num { display: inline-block; width: 48px; color: var(--code-num); user-select: none; }
    .code .empty { color: var(--code-num); padding: 40px 20px; font-size: 13px; }

    /* journey: dot-bulleted rows with hairline separators */
    .journey .step { display: flex; gap: 14px; align-items: baseline; padding: 12px 20px; border-bottom: 1px solid var(--line-soft); }
    .journey .step:last-child { border-bottom: 0; }
    .journey .dot { width: 7px; height: 7px; border-radius: 99px; background: var(--brand); flex: none; transform: translateY(-1px); }
    .journey .step:last-child .dot { background: var(--red); }
    .journey .type { font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); min-width: 92px; }
    .journey .what { font-size: 13px; overflow-wrap: anywhere; }
    .journey .meta-inline { color: var(--faint); font-size: 12px; }
    .journey .step-block { border-bottom: 1px solid var(--line-soft); }
    .journey .step-block:last-child { border-bottom: 0; }
    .journey .step-block .step { border-bottom: 0; cursor: pointer; list-style: none; }
    .journey .step-block .step::-webkit-details-marker { display: none; }
    .journey .step-block .step.static { cursor: default; }
    .journey .step-block .step:not(.static):hover { background: var(--panel-2); }
    .journey .step-block[open] .step { background: var(--panel-2); }
    .journey .step-detail { padding: 2px 20px 14px 46px; font-size: 12px; }
    .journey .step-detail .ed { color: var(--faint); text-decoration: none; }
    .journey .crumb-data, .peek-tree {
        margin: 8px 0 4px; padding: 10px 16px; background: var(--code-bg); color: var(--code-text);
        border-radius: 6px; font-size: 12px; line-height: 1.7; overflow: auto; max-height: 300px;
        font-family: "JetBrains Mono", ui-monospace, monospace;
    }
    .peek-tree { margin: 0 20px 16px 40px; }
    .jt, .jt-row { padding-left: 18px; }
    .jt-root > .jt, .jt-root > .jt-row { padding-left: 0; }
    .jt > *:not(summary) { border-left: 1px solid color-mix(in srgb, var(--code-num) 40%, transparent); margin-left: 3px; }
    .jt summary { cursor: pointer; list-style: none; user-select: none; margin-left: -18px; padding-left: 18px; }
    .jt summary::-webkit-details-marker { display: none; }
    .jt summary::before { content: "▸"; color: var(--code-num); display: inline-block; width: 14px; margin-left: -14px; font-size: 10px; }
    .jt[open] > summary::before { content: "▾"; }
    .jt summary:hover .jt-k { color: var(--code-text); }
    .jt-k { color: var(--muted); }
    .jt-b { color: var(--code-num); font-size: 11px; }
    .jt-s { color: #A8C7A0; }
    .jt-n { color: var(--gold); }
    .jt-x { color: var(--blue); }
    .jt-cut { color: var(--code-num); font-style: italic; }
    .journey .step-detail .ed:hover { color: var(--brand); }

    /* context: three-column hairline grid */
    .ctxgrid { display: grid; grid-template-columns: repeat(3, 1fr); }
    @media (max-width: 900px) { .ctxgrid { grid-template-columns: 1fr; } }
    .ctxcol { padding: 16px 20px 20px; border-right: 1px solid var(--line); }
    .ctxcol:last-child { border-right: 0; }
    @media (max-width: 900px) { .ctxcol { border-right: 0; border-bottom: 1px solid var(--line); } .ctxcol:last-child { border-bottom: 0; } }
    .ctxcol h3 { font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 10.5px; letter-spacing: .12em; text-transform: uppercase; color: var(--faint); margin: 0 0 4px; font-weight: 500; }
    .ctxcol h3 .rule { display: block; width: 26px; height: 2px; background: var(--brand); margin: 8px 0 10px; }
    .kvrow { display: flex; align-items: baseline; gap: 10px; font-size: 12.5px; padding: 3px 0; }
    .kvrow .k { color: var(--muted); white-space: nowrap; }
    .kvrow .leader { flex: 1; border-bottom: 1px dotted var(--line); transform: translateY(-4px); }
    .kvrow .v { overflow-wrap: anywhere; text-align: right; max-width: 58%; }
    .kvrow .v.masked { color: var(--amber); background: var(--amber-soft); border-radius: 5px; padding: 0 8px; font-size: 11px; letter-spacing: .1em; }

    /* caused-by chain */
    .cause { border-bottom: 1px solid var(--line-soft); }
    .cause:last-child { border-bottom: 0; }
    .causehead { display: flex; gap: 12px; align-items: baseline; padding: 12px 20px 2px; }
    .causekind { font-size: 12px; color: var(--red); font-weight: 700; }
    .causemsg { font-size: 12.5px; color: var(--text); overflow-wrap: anywhere; }
    .causehead .ed { margin-left: auto; color: var(--faint); text-decoration: none; font-size: 11px; }
    .causehead .ed:hover { color: var(--brand); }
    .causeloc { font-size: 11.5px; color: var(--muted); padding: 0 20px 10px; overflow-wrap: anywhere; }
    .causecode { min-height: 0; }

    /* peeks */
    .peek { border-bottom: 1px solid var(--line-soft); }
    .peek:last-child { border-bottom: 0; }
    .peek summary { display: flex; gap: 14px; align-items: baseline; padding: 11px 20px; cursor: pointer; list-style: none; }
    .peek summary::before { content: "▸"; color: var(--faint); font-size: 10px; }
    .peek[open] summary::before { content: "▾"; }
    .peek summary:hover { background: var(--panel-2); }
    .peekname { font-size: 12.5px; font-weight: 600; }
    .peekhint { font-size: 11.5px; color: var(--faint); overflow-wrap: anywhere; }
    .peek pre { margin: 0; padding: 12px 20px 16px 40px; font-size: 12px; color: var(--muted); overflow-x: auto; }

    /* timings */
    .timings .span { display: flex; gap: 14px; align-items: center; padding: 9px 20px; border-bottom: 1px solid var(--line-soft); font-size: 12px; }
    .timings .span:last-child { border-bottom: 0; }
    .spanname { min-width: 160px; overflow-wrap: anywhere; }
    .spanbar { flex: 1; height: 6px; background: var(--panel-2); border-radius: 3px; overflow: hidden; }
    .spanbar i { display: block; height: 100%; background: linear-gradient(90deg, var(--brand), var(--gold)); border-radius: 3px; }
    .spanms { color: var(--muted); min-width: 70px; text-align: right; }

    .toast {
        position: fixed; bottom: 24px; left: 50%; transform: translate(-50%, 80px); transition: transform .25s ease;
        background: var(--brand); color: #FFF6EE; font-weight: 600; font-size: 13px; border-radius: 8px; padding: 10px 18px;
    }
    .toast.show { transform: translate(-50%, 0); }
    ::selection { background: var(--brand); color: #FFF6EE; }
</style>
</head>
<body>
<div class="wrap">
    <div class="bar">
        <div class="brand display"><?php if ($logo): ?><img class="leaf" src="<?= $logo ?>" alt="Leaf PHP" width="24" height="24"><?php else: ?><span class="leaf"></span><?php endif; ?> Leaf PHP</div>
        <span class="eyebrow">crash report</span>
        <div class="spacer"></div>
        <button class="btn" onclick="copyMarkdown()">⧉ Copy as Markdown</button>
        <div class="ai-menu" id="ai-menu">
            <button class="btn tinted" onclick="document.getElementById('ai-menu').classList.toggle('open')">⌬ Open with AI <span class="caret">⌃</span></button>
            <div class="ai-panel">
                <div class="ai-label">ask about this crash</div>
                <a class="ai-item" href="<?= $e(Leaf\Crash\Ai::claudeUrl($prompt)) ?>" target="_blank"><span class="ai-ico">AI</span> Open in Claude <span class="ext">↗</span></a>
                <a class="ai-item" href="<?= $e(Leaf\Crash\Ai::chatgptUrl($prompt)) ?>" target="_blank"><span class="ai-ico">◌</span> Open in ChatGPT <span class="ext">↗</span></a>
                <button class="ai-item" onclick="copyPrompt()"><span class="ai-ico">⧉</span> Copy AI prompt</button>
                <div class="ai-label" style="margin-top: 8px;">take it with you</div>
                <button class="ai-item" onclick="downloadReport()"><span class="ai-ico">↓</span> Download report (JSON)</button>
                <?php if ($curl): ?>
                    <button class="ai-item" onclick="copyCurl()"><span class="ai-ico">$</span> Copy request as cURL</button>
                <?php endif; ?>
                <?php if (($frame = $report->applicationFrame()) && $frame->file): ?>
                    <a class="ai-item" href="vscode://file/<?= $e($frame->file) ?>:<?= $e($frame->line) ?>"><span class="ai-ico">‹›</span> Open crash line in editor</a>
                <?php endif; ?>
            </div>
        </div>
        <button class="btn" onclick="toggleTheme()" title="Toggle theme">☾</button>
    </div>

    <div class="hero">
        <span class="pill"><span class="dot"></span><?= $e($report->exception) ?> · <?= $e($report->level) ?></span>
        <h1 class="display"><?= $e($report->message) ?></h1>
        <div class="facts mono">
            <div class="fact"><span class="k">when</span><span class="leader"></span><span class="v"><?= $e($report->occurredAt) ?></span></div>
            <?php if (!empty($report->request['method']) || !empty($report->request['url'])): ?>
                <div class="fact"><span class="k">request</span><span class="leader"></span><span class="v hl"><?= $e(trim(($report->request['method'] ?? '') . ' ' . ($report->request['url'] ?? ''))) ?></span></div>
            <?php endif; ?>
            <?php if ($frame = $report->applicationFrame()): ?>
                <div class="fact"><span class="k">where</span><span class="leader"></span><span class="v hl"><?= $e($frame->file) ?>:<?= $e($frame->line) ?></span></div>
            <?php endif; ?>
            <div class="fact"><span class="k">fingerprint</span><span class="leader"></span><span class="v"><?= $e($report->fingerprint) ?></span></div>
            <div class="fact"><span class="k">report</span><span class="leader"></span><span class="v"><?= $e($report->id) ?></span></div>
            <?php if ($report->occurrences !== null): ?>
                <div class="fact"><span class="k">seen</span><span class="leader"></span><span class="v hl"><?= $e($report->occurrences) ?> time<?= $report->occurrences === 1 ? '' : 's' ?></span></div>
            <?php endif; ?>
            <?php foreach (($report->app ?: []) as $key => $value): if (!is_scalar($value)) {
                continue;
            } ?>
                <div class="fact"><span class="k"><?= $e($key) ?></span><span class="leader"></span><span class="v"><?= $e($value) ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($options['solution'])): ?>
        <div class="solution">
            <span class="tick tl"></span><span class="tick tr"></span><span class="tick bl"></span><span class="tick br"></span>
            <span class="spark">✦</span>
            <div><span class="eyebrow" style="display:block; margin-bottom:6px;">possible fix</span><?= $e($options['solution']) ?></div>
        </div>
    <?php endif; ?>

    <div class="section">
        <div class="sechead">
            <span class="index"><b>0<?= ++$sectionNumber ?></b></span><span class="rule"></span>
            <span style="flex:1"></span>
            <span class="label">stack · <?= count($frames) ?> frames</span>
        </div>
        <div class="stackgrid">
            <div class="frame-list mono" id="frames">
                <?php foreach ($groups as $g => $group): ?>
                    <?php if ($group['type'] === 'vendor' && count($group['frames']) > 1): ?>
                        <div class="vendor-group collapsed" id="vg-<?= $g ?>">
                            <div class="vendor-toggle" onclick="document.getElementById('vg-<?= $g ?>').classList.toggle('collapsed')">
                                ▸ <?= count($group['frames']) ?> vendor frames
                            </div>
                            <?php foreach ($group['frames'] as $item): ?>
                                <div class="frame" data-index="<?= $item['index'] ?>" onclick="selectFrame(<?= $item['index'] ?>)">
                                    <div class="fn"><?= $e($item['frame']->callable() ?? '{main}') ?></div>
                                    <div class="loc"><?= $e(($item['frame']->file ?? '[internal]') . ':' . $item['frame']->line) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($group['frames'] as $item): ?>
                            <div class="frame<?= $item['index'] === $activeIndex ? ' active' : '' ?>" data-index="<?= $item['index'] ?>" onclick="selectFrame(<?= $item['index'] ?>)">
                                <div class="fn"><?= $e($item['frame']->callable() ?? '{main}') ?><?php if ($item['frame']->file): ?><a class="ed" href="<?= $e($editorUrl($item['frame']->file, $item['frame']->line)) ?>" onclick="event.stopPropagation()" title="Open in editor">‹›</a><?php endif; ?></div>
                                <div class="loc"><?= $e(($item['frame']->file ?? '[internal]') . ':' . $item['frame']->line) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div>
                <div class="code mono" id="code-pane"></div>
            </div>
        </div>
    </div>

    <?php if ($report->breadcrumbs): ?>
    <div class="section">
        <div class="sechead">
            <span class="index"><b>0<?= ++$sectionNumber ?></b></span><span class="rule"></span>
            <span style="flex:1"></span>
            <span class="label">user journey</span>
        </div>
        <div class="journey">
            <?php foreach ($report->breadcrumbs as $ci => $crumb): ?>
                <?php
                $crumbMeta = is_array($crumb) ? ($crumb['meta'] ?? []) : [];
                $crumbOrigin = is_array($crumb) ? ($crumb['origin'] ?? null) : null;
                $crumbAt = is_array($crumb) ? ($crumb['at'] ?? null) : null;
                $expandable = $crumbMeta || $crumbOrigin || $crumbAt;
                ?>
                <details class="step-block">
                    <summary class="step<?= $expandable ? '' : ' static' ?>">
                        <span class="dot"></span>
                        <span class="type"><?= $e(is_array($crumb) ? ($crumb['type'] ?? 'action') : 'log') ?></span>
                        <span class="what"><?= $e(is_array($crumb) ? ($crumb['message'] ?? '') : $crumb) ?></span>
                    </summary>
                    <?php if ($expandable): ?>
                        <div class="step-detail mono">
                            <?php if ($crumbMeta): ?>
                                <?php $metaJson = json_encode($crumbMeta, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR); ?>
                                <?php if (mb_strlen($metaJson) <= 80): ?>
                                    <div class="kvrow"><span class="k">data</span><span class="leader"></span><span class="v"><?= $e($metaJson) ?></span></div>
                                <?php else: ?>
                                    <div class="kvrow"><span class="k">data</span><span class="leader"></span><span class="v"><?= $e(count($crumbMeta)) ?> field<?= count($crumbMeta) === 1 ? '' : 's' ?></span></div>
                                    <div class="crumb-data"><?= $tree($crumbMeta) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($crumbAt): ?>
                                <div class="kvrow"><span class="k">at</span><span class="leader"></span><span class="v"><?= $e($crumbAt) ?></span></div>
                            <?php endif; ?>
                            <?php if ($crumbOrigin && !empty($crumbOrigin['callable'])): ?>
                                <div class="kvrow"><span class="k">inside</span><span class="leader"></span><span class="v"><?= $e($crumbOrigin['callable']) ?></span></div>
                            <?php endif; ?>
                            <?php if ($crumbOrigin && !empty($crumbOrigin['file'])): ?>
                                <div class="kvrow"><span class="k">recorded</span><span class="leader"></span><span class="v"><?= $e($crumbOrigin['file']) ?>:<?= $e($crumbOrigin['line']) ?> <a class="ed" href="<?= $e($editorUrl($crumbOrigin['file'], $crumbOrigin['line'])) ?>">‹›</a></span></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="section">
        <div class="sechead">
            <span class="index"><b>0<?= ++$sectionNumber ?></b></span><span class="rule"></span>
            <span style="flex:1"></span>
            <span class="label">context</span>
        </div>
        <div class="ctxgrid mono">
            <?php
            $sections = array_filter(['request' => $report->request, 'user' => $report->user, 'app' => $report->app]);
foreach ($sections as $label => $section): ?>
                <div class="ctxcol">
                    <h3><?= $e($label) ?><span class="rule"></span></h3>
                    <?php foreach ($section as $key => $value):
                        $flat = is_array($value) ? $value : [$key => $value];
                        foreach ($flat as $subKey => $subValue):
                            $display = is_scalar($subValue) ? (string) $subValue : json_encode($subValue);
                            $isMasked = $display === \Leaf\Crash\Redactor::MASK; ?>
                            <div class="kvrow">
                                <span class="k"><?= $e(is_array($value) ? "$key.$subKey" : $key) ?></span>
                                <span class="leader"></span>
                                <span class="v<?= $isMasked ? ' masked' : '' ?>"><?= $isMasked ? 'redacted' : $e($display) ?></span>
                            </div>
                        <?php endforeach;
                    endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($report->chain): ?>
    <div class="section">
        <div class="sechead">
            <span class="index"><b>0<?= ++$sectionNumber ?></b></span><span class="rule"></span>
            <span style="flex:1"></span>
            <span class="label">caused by</span>
        </div>
        <?php foreach ($report->chain as $cause): ?>
            <div class="cause">
                <div class="causehead mono">
                    <span class="causekind"><?= $e($cause['exception']) ?></span>
                    <span class="causemsg"><?= $e($cause['message']) ?></span>
                    <a class="ed" href="<?= $e($editorUrl($cause['file'], $cause['line'])) ?>" title="Open in editor">‹›</a>
                </div>
                <div class="causeloc mono"><?= $e($cause['file']) ?>:<?= $e($cause['line']) ?></div>
                <?php if (!empty($cause['excerpt'])): ?>
                    <div class="code mono causecode">
                        <?php foreach ($cause['excerpt'] as $number => $line): ?>
                            <div class="row<?= $number === $cause['line'] ? ' hit' : '' ?>"><span class="num"><?= $number ?></span><?= $e($line) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($report->peeks): ?>
    <div class="section">
        <div class="sechead">
            <span class="index"><b>0<?= ++$sectionNumber ?></b></span><span class="rule"></span>
            <span style="flex:1"></span>
            <span class="label">peeked values</span>
        </div>
        <div class="peeks mono">
            <?php foreach ($report->peeks as $name => $value): ?>
                <details class="peek">
                    <summary><span class="peekname"><?= $e($name) ?></span><span class="peekhint"><?= $e(is_scalar($value) || $value === null ? var_export($value, true) : gettype($value)) ?></span></summary>
                    <div class="peek-tree"><?= is_array($value) ? $tree($value) : $tree(['value' => $value]) ?></div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($report->timings): ?>
    <div class="section">
        <div class="sechead">
            <span class="index"><b>0<?= ++$sectionNumber ?></b></span><span class="rule"></span>
            <span style="flex:1"></span>
            <span class="label">timings</span>
        </div>
        <div class="timings mono">
            <?php $maxMs = max(array_column($report->timings, 'ms')) ?: 1; ?>
            <?php foreach ($report->timings as $span): ?>
                <div class="span">
                    <span class="spanname"><?= $e($span['name']) ?></span>
                    <span class="spanbar"><i style="width: <?= max(2, round($span['ms'] / $maxMs * 100)) ?>%"></i></span>
                    <span class="spanms"><?= $e($span['ms']) ?>ms</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="toast" id="toast">Copied. Paste it into your AI assistant</div>

<script>
    const FRAMES = <?= $framesJson ?>;
    const MARKDOWN = <?= json_encode($markdown, JSON_PARTIAL_OUTPUT_ON_ERROR) ?>;
    const PROMPT = <?= json_encode($prompt, JSON_PARTIAL_OUTPUT_ON_ERROR) ?>;
    const REPORT_JSON = <?= json_encode(json_encode($export, JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR), JSON_PARTIAL_OUTPUT_ON_ERROR) ?>;
    const REPORT_ID = <?= json_encode($report->id) ?>;
    const CURL = <?= json_encode($curl, JSON_PARTIAL_OUTPUT_ON_ERROR) ?>;

    function esc(s) { const d = document.createElement('span'); d.textContent = s ?? ''; return d.innerHTML; }

    function selectFrame(index) {
        document.querySelectorAll('.frame').forEach(el => el.classList.toggle('active', +el.dataset.index === index));

        const f = FRAMES[index];
        const pane = document.getElementById('code-pane');
        const lines = Object.entries(f.excerpt);

        pane.innerHTML = lines.length
            ? lines.map(([n, code]) =>
                `<div class="row${+n === f.line ? ' hit' : ''}"><span class="num">${n}</span>${esc(code)}</div>`).join('')
            : '<div class="empty">No source available for this frame.</div>';
    }

    function copyPrompt() {
        navigator.clipboard.writeText(PROMPT).then(() => toast('Prompt copied. Paste it into any assistant'));
    }

    function copyCurl() {
        navigator.clipboard.writeText(CURL).then(() => toast('cURL copied. Replay the request from your terminal'));
    }

    function downloadReport() {
        const blob = new Blob([REPORT_JSON], { type: 'application/json' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'crash-' + REPORT_ID + '.json';
        a.click();
        URL.revokeObjectURL(a.href);
    }

    document.addEventListener('click', (e) => {
        const menu = document.getElementById('ai-menu');
        if (menu && !menu.contains(e.target)) menu.classList.remove('open');
    });

    function toast(message) {
        const t = document.getElementById('toast');
        t.textContent = message;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 2200);
    }

    function copyMarkdown() {
        navigator.clipboard.writeText(MARKDOWN).then(() => toast('Copied. Paste it into your AI assistant'));
    }

    function toggleTheme() {
        const b = document.body;
        const effective = b.dataset.theme
            || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        b.dataset.theme = effective === 'dark' ? 'light' : 'dark';
    }

    selectFrame(<?= $activeIndex ?>);
</script>
</body>
</html>
