<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light">
    <title>@yield('title') — Тендер-Радар</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1D5BD8">
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Тендер-Радар">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <style>
        :root { --bg:#F3F4F6; --card:#FFFFFF; --ink:#14181F; --ink2:#3A4250; --muted:#5B6472; --line:#E3E6EA; --field:#D5D9DF; --chip:#EEF0F3; --accent:#1D5BD8; --accentbg:#EAF0FD; --hot:#C2410C; --hotbg:#FFF4ED; --hotink:#7C2D12; --ok:#166534; --okbg:#E8F5EC; --err:#B91C1C; --errbg:#FEECEC; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font:15px/1.5 Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        a { color:var(--accent); text-decoration:none; }
        .top { background:var(--card); border-bottom:1px solid var(--line); padding:16px 20px 12px; display:flex; flex-direction:column; gap:12px; position:sticky; top:0; z-index:5; }
        .top-row { display:flex; justify-content:space-between; align-items:center; gap:12px; }
        .top h1 { margin:0; font-size:22px; font-weight:700; letter-spacing:-0.3px; }
        .top .sub { margin:0; font-size:13px; color:var(--muted); }
        .back { width:44px; height:44px; margin-left:-12px; display:inline-flex; align-items:center; justify-content:center; color:var(--ink); }
        main { max-width:640px; margin:0 auto; padding:12px 16px 112px; display:flex; flex-direction:column; gap:10px; }
        main.narrow-bottom { padding-bottom:48px; }
        main.with-actions { padding-bottom:220px; }
        h2 { font-size:15px; margin:0; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:14px; display:flex; flex-direction:column; gap:10px; }
        .muted { color:var(--muted); font-size:13px; margin:0; }
        .label { font-size:12px; color:var(--muted); }
        .section-title { margin:0; font-size:13px; font-weight:600; color:var(--muted); }
        label { font-size:13px; font-weight:600; color:var(--muted); display:flex; flex-direction:column; gap:6px; }
        input[type=email], input[type=password], input[type=text], input[type=date], textarea { min-height:44px; border:1px solid var(--field); border-radius:10px; padding:0 12px; font:inherit; color:var(--ink); background:#fff; width:100%; }
        textarea { padding:10px 12px; line-height:1.5; resize:vertical; }
        select { min-height:44px; border:1px solid var(--field); border-radius:10px; padding:0 10px; font:inherit; background:#fff; color:var(--ink); }
        .check { flex-direction:row; align-items:center; gap:8px; font-weight:500; color:var(--ink); }
        .check input { width:20px; height:20px; }
        button, .button { min-height:44px; border:0; border-radius:12px; padding:0 18px; font:inherit; font-weight:600; cursor:pointer; background:var(--accent); color:#fff; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; text-align:center; }
        button.secondary, .button.secondary { background:#fff; color:var(--ink); border:1px solid var(--field); }
        button.dark, .button.dark { background:var(--ink); color:#fff; }
        button:disabled, .button.disabled { opacity:.45; cursor:not-allowed; }
        .icon-button { width:44px; height:44px; padding:0; border-radius:12px; border:1px solid var(--line); background:#fff; color:var(--ink); }
        .row { display:flex; gap:8px; flex-wrap:wrap; }
        .grid2 { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px; }
        .grid3 { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:8px; }
        .flash { border-radius:12px; padding:10px 12px; font-size:14px; }
        .flash.ok { background:var(--okbg); color:var(--ok); }
        .flash.err { background:var(--errbg); color:var(--err); }
        .badge { display:inline-block; font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px; background:var(--chip); color:var(--ink2); }
        .badge.on { background:var(--okbg); color:var(--ok); }
        .badge.off { background:var(--chip); color:var(--ink2); }
        .badge.hot { background:var(--hot); color:#fff; letter-spacing:.3px; }
        .badge.new, .badge.brief_received { background:var(--accentbg); color:var(--accent); }
        .badge.offer_sent, .badge.negotiation { background:var(--okbg); color:var(--ok); }
        .badge.contract, .badge.in_progress { background:var(--hotbg); color:#9A3412; }
        .badge.done_paid { background:var(--ok); color:#fff; }
        .badge.refused, .badge.dismissed { background:var(--chip); color:var(--ink2); }
        .status-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:8px; }
        .status-grid button { min-height:44px; padding:0 6px; font-size:13px; }
        .status-grid button[aria-pressed=true] { background:var(--ink); color:#fff; border-color:var(--ink); }
        .scroll-x { display:flex; gap:8px; overflow-x:auto; margin:0 -20px; padding:0 20px 2px; scrollbar-width:none; }
        .scroll-x .stat { flex:none; min-width:96px; padding:0 8px; }
        table.report { width:100%; border-collapse:collapse; font-size:13px; }
        table.report th { text-align:left; font-weight:600; color:var(--muted); font-size:12px; padding:6px 4px; border-bottom:1px solid var(--line); }
        table.report td { padding:8px 4px; border-bottom:1px solid var(--chip); vertical-align:top; }
        table.report .num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .bar { height:6px; border-radius:3px; background:var(--chip); overflow:hidden; }
        .bar i { display:block; height:100%; background:var(--accent); }
        .tag { font-size:12px; font-weight:600; color:var(--accent); background:var(--accentbg); padding:4px 10px; border-radius:8px; }
        .tags { display:flex; gap:6px; flex-wrap:wrap; }
        .error { color:var(--err); font-size:13px; margin:0; }
        .chips { display:flex; gap:8px; overflow-x:auto; margin:0 -20px; padding:0 20px 2px; scrollbar-width:none; }
        .chip { flex:none; min-height:36px; padding:0 14px; border-radius:18px; font-size:13px; font-weight:600; display:inline-flex; align-items:center; gap:6px; background:#fff; color:var(--ink2); border:1px solid var(--field); }
        .chip.active { background:var(--ink); color:#fff; border-color:var(--ink); }
        .chip .n { opacity:.75; }
        .tender h2 { font-size:15px; font-weight:600; line-height:1.35; }
        .sum { font-size:18px; font-weight:700; white-space:nowrap; }
        .between { display:flex; justify-content:space-between; align-items:center; gap:8px; }
        .kv { display:flex; flex-direction:column; gap:2px; }
        .kv b { font-weight:600; }
        .note-hot { background:var(--hotbg); color:var(--hotink); border-radius:12px; padding:10px 12px; font-size:13px; line-height:1.45; }
        .contacts div { display:flex; justify-content:space-between; gap:8px; font-size:14px; }
        .contacts span:first-child { color:var(--muted); }
        .contacts span:last-child { font-weight:500; text-align:right; word-break:break-word; }
        .stat { min-height:60px; border-radius:12px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px; background:#fff; color:var(--ink2); border:1px solid var(--field); }
        .stat b { font-size:18px; }
        .stat span { font-size:11px; font-weight:600; }
        .stat.active { background:var(--ink); color:#fff; border-color:var(--ink); }
        .segmented { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:4px; background:var(--chip); padding:4px; border-radius:12px; }
        .segmented button { min-height:40px; border-radius:9px; background:transparent; color:var(--ink2); font-size:13px; }
        .segmented button[aria-pressed=true] { background:#fff; color:var(--ink); box-shadow:0 1px 2px rgba(20,24,31,.12); }
        .letter { font-size:14px; line-height:1.55; white-space:pre-line; margin:0; }
        .actionbar { position:fixed; left:0; right:0; bottom:0; background:#fff; border-top:1px solid var(--line); padding:12px 16px calc(16px + env(safe-area-inset-bottom)); z-index:6; }
        .actionbar > div { max-width:640px; margin:0 auto; display:flex; flex-direction:column; gap:8px; }
        .tabbar { position:fixed; left:0; right:0; bottom:0; height:calc(68px + env(safe-area-inset-bottom)); padding-bottom:env(safe-area-inset-bottom); background:#fff; border-top:1px solid var(--line); display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); z-index:6; }
        .tabbar a { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; color:var(--muted); font-size:11px; font-weight:500; }
        .tabbar a[aria-current=page] { color:var(--accent); font-weight:600; }
        .empty { text-align:center; padding:32px 16px; }
        .pager { display:flex; justify-content:center; }
        details summary { cursor:pointer; font-weight:600; min-height:44px; display:flex; align-items:center; }
    </style>
</head>
<body>
@hasSection('top')
    <header class="top">@yield('top')</header>
@endif
<main class="@yield('main_class')">
    @if (session('status'))
        <div class="flash ok" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="flash err" role="alert">{{ session('error') }}</div>
    @endif
    @yield('content')
</main>
@yield('bottom')
@auth
    @sectionMissing('bottom')
        @include('partials.tabbar')
    @endif
@endauth
<script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
</script>
@stack('scripts')
</body>
</html>
