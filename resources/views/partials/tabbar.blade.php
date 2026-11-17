@php
    $tabs = [
        ['route' => 'home', 'active' => ['home', 'tenders.*', 'estimates.*'], 'label' => 'Тендеры', 'icon' => '<path d="M4 6h16M4 12h16M4 18h10"/>'],
        ['route' => 'deals', 'active' => ['deals', 'deals.*', 'funnel'], 'label' => 'Сделки', 'icon' => '<path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"/>'],
        ['route' => 'template', 'active' => ['template'], 'label' => 'Шаблон КП', 'icon' => '<path d="M4 4h16v16H4z"/><path d="M4 8l8 5 8-5"/>'],
        ['route' => 'settings', 'active' => ['settings', 'settings.*', 'prices'], 'label' => 'Настройки', 'icon' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>'],
    ];
@endphp
<nav class="tabbar" aria-label="Разделы">
    @foreach ($tabs as $tab)
        <a href="{{ route($tab['route']) }}" @if (request()->routeIs(...$tab['active'])) aria-current="page" @endif>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $tab['icon'] !!}</svg>
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
