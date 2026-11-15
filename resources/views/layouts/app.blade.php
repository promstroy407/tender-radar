<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
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
        :root { --bg:#F3F4F6; --card:#FFFFFF; --ink:#14181F; --muted:#5B6472; --line:#E3E6EA; --accent:#1D5BD8; --ok:#166534; --okbg:#E8F5EC; --err:#B91C1C; --errbg:#FEECEC; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font:15px/1.5 Inter, system-ui, -apple-system, "Segoe UI", sans-serif; }
        header { background:var(--card); border-bottom:1px solid var(--line); padding:14px 20px; display:flex; justify-content:space-between; align-items:center; gap:12px; }
        header a { color:var(--ink); text-decoration:none; font-weight:700; }
        main { max-width:640px; margin:0 auto; padding:20px 16px 48px; display:flex; flex-direction:column; gap:14px; }
        h1 { font-size:22px; margin:0; }
        h2 { font-size:15px; margin:0 0 6px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:16px; display:flex; flex-direction:column; gap:12px; }
        .muted { color:var(--muted); font-size:13px; margin:0; }
        label { font-size:13px; font-weight:600; color:var(--muted); display:flex; flex-direction:column; gap:6px; }
        input[type=email], input[type=password], input[type=text] { min-height:44px; border:1px solid #D5D9DF; border-radius:10px; padding:0 12px; font:inherit; color:var(--ink); }
        .check { flex-direction:row; align-items:center; gap:8px; font-weight:500; }
        .check input { width:20px; height:20px; }
        button, .button { min-height:44px; border:0; border-radius:12px; padding:0 18px; font:inherit; font-weight:600; cursor:pointer; background:var(--accent); color:#fff; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }
        button.secondary { background:#fff; color:var(--ink); border:1px solid #D5D9DF; }
        .row { display:flex; gap:8px; flex-wrap:wrap; }
        .flash { border-radius:12px; padding:10px 12px; font-size:14px; }
        .flash.ok { background:var(--okbg); color:var(--ok); }
        .flash.err { background:var(--errbg); color:var(--err); }
        .badge { display:inline-block; font-size:12px; font-weight:700; padding:2px 8px; border-radius:6px; }
        .badge.on { background:var(--okbg); color:var(--ok); }
        .badge.off { background:#EEF0F3; color:#3A4250; }
        .error { color:var(--err); font-size:13px; margin:0; }
    </style>
</head>
<body>
<header>
    <a href="{{ route('home') }}">Тендер-Радар</a>
    @auth
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="secondary">Выйти</button>
        </form>
    @endauth
</header>
<main>
    @if (session('status'))
        <div class="flash ok" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="flash err" role="alert">{{ session('error') }}</div>
    @endif
    @yield('content')
</main>
<script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
</script>
@stack('scripts')
</body>
</html>
