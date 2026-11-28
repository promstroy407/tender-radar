@extends('layouts.app')

@section('title', 'Тендеры')

@section('top')
    <div class="top-row">
        <div>
            <h1>Тендеры</h1>
            <p class="sub">Новые победители, по которым КП ещё не отправлено</p>
        </div>
        <a class="button icon-button" href="{{ request()->fullUrl() }}" aria-label="Обновить список">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg>
        </a>
    </div>
    @php
        // Регион, даты загрузки и переключатели сохраняются при смене фильтра.
        $scope = array_filter(['region' => $region, 'from' => $loadedFrom, 'to' => $loadedTo]);
        $toggles = array_filter(['docs' => $withDocs ? 1 : null, 'unseen' => $unseen ? 1 : null]) + $scope;
        $kind = ($active->value === 'all' ? [] : ['filter' => $active->value]) + $scope;
    @endphp
    <nav class="chips" aria-label="Фильтр">
        @foreach ($filters as $filter)
            <a class="chip {{ $filter === $active ? 'active' : '' }}"
               href="{{ route('home', ($filter->value === 'all' ? [] : ['filter' => $filter->value]) + $toggles) }}"
               @if ($filter === $active) aria-current="page" @endif>
                {{ $filter->label() }} <span class="n">{{ $counts[$filter->value] }}</span>
            </a>
        @endforeach
    </nav>
    <nav class="chips toggles" aria-label="Дополнительно">
        <a class="chip {{ $withDocs ? 'active' : '' }}" href="{{ route('home', $kind + array_filter(['docs' => $withDocs ? null : 1, 'unseen' => $unseen ? 1 : null])) }}" @if ($withDocs) aria-pressed="true" @endif>С проектом или сметой</a>
        <a class="chip {{ $unseen ? 'active' : '' }}" href="{{ route('home', $kind + array_filter(['docs' => $withDocs ? 1 : null, 'unseen' => $unseen ? null : 1])) }}" @if ($unseen) aria-pressed="true" @endif>Непросмотренные</a>
    </nav>
    <details class="feed-filter" @if ($scope !== [] || $errors->hasAny(['region', 'from', 'to'])) open @endif>
        <summary>Регион и дата загрузки@if ($scope !== []) <span class="n">· выбрано</span>@endif</summary>
        <form method="get" action="{{ route('home') }}">
            @if ($active->value !== 'all') <input type="hidden" name="filter" value="{{ $active->value }}"> @endif
            @if ($withDocs) <input type="hidden" name="docs" value="1"> @endif
            @if ($unseen) <input type="hidden" name="unseen" value="1"> @endif
            <label>Регион
                <select name="region">
                    <option value="">все регионы</option>
                    @foreach ($regions as $item)
                        <option value="{{ $item->code }}" @selected($region === $item->code)>{{ $item->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="row" style="flex-wrap:nowrap">
                <label style="flex:1;min-width:0">Загружены с
                    <input type="date" name="from" value="{{ $loadedFrom }}">
                </label>
                <label style="flex:1;min-width:0">по
                    <input type="date" name="to" value="{{ $loadedTo }}">
                </label>
            </div>
            @error('to') <p class="error">Дата «по» должна быть не раньше даты «с».</p> @enderror
            <div class="row">
                <button type="submit" class="small">Показать</button>
                @if ($scope !== [])
                    <a class="button secondary small" href="{{ route('home', array_diff_key($kind, $scope) + array_diff_key($toggles, $scope)) }}">Сбросить</a>
                @endif
            </div>
        </form>
    </details>
@endsection

@section('content')
    @forelse ($contracts as $contract)
        <article class="card tender {{ $contract->viewed ? 'viewed' : '' }}">
            <div class="tags">
                @if ($contract->viewed)
                    <span class="badge seen">Просмотрено</span>
                @endif
                @if ($contract->isHot())
                    <span class="badge hot">ГОРЯЧИЙ · СУБПОДРЯД СМП</span>
                @endif
                <span class="badge">{{ $contract->law->label() }}</span>
                <span class="badge">{{ $contract->region->name }}</span>
            </div>
            <h2>{{ $contract->subject }}</h2>
            <div class="between">
                <span class="sum">{{ $money->format($contract->amount_kopecks) }}</span>
                <span class="muted">{{ $contract->kind->label() }}</span>
            </div>
            <p class="muted" style="color:var(--ink2)">Победитель: <b>{{ $contract->company->name }}</b></p>
            <p class="muted" style="font-size:12px">Загружен {{ $contract->created_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }} · заключён {{ $contract->signed_at->format('d.m.Y') }}</p>
            <div class="between">
                <span style="font-size:12px;color:var(--accent);font-weight:500">
                    {{ $contract->sections->map(fn ($s) => $s->shortLabel())->implode(' · ') }}
                </span>
                <a class="button dark" href="{{ route('tenders.show', $contract) }}">Открыть</a>
            </div>
        </article>
    @empty
        <div class="card empty">
            <h2>{{ $unseen || $withDocs || $scope !== [] ? 'Под эти условия тендеров нет' : 'Новых тендеров нет' }}</h2>
            <p class="muted">Сбор из ЕИС подключится, когда будет токен. Чтобы посмотреть, как всё работает, загрузите демо-тендеры в «Настройках».</p>
            <div class="row" style="justify-content:center">
                <a class="button secondary" href="{{ route('settings') }}">Открыть настройки</a>
            </div>
        </div>
    @endforelse

    @if ($contracts->hasMorePages())
        <div class="pager">
            <a class="button secondary" href="{{ $contracts->nextPageUrl() }}">Показать ещё</a>
        </div>
    @endif
@endsection
