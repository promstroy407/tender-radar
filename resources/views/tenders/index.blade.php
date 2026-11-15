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
    <nav class="chips" aria-label="Фильтр">
        @foreach ($filters as $filter)
            <a class="chip {{ $filter === $active ? 'active' : '' }}"
               href="{{ route('home', $filter->value === 'all' ? [] : ['filter' => $filter->value]) }}"
               @if ($filter === $active) aria-current="page" @endif>
                {{ $filter->label() }} <span class="n">{{ $counts[$filter->value] }}</span>
            </a>
        @endforeach
    </nav>
@endsection

@section('content')
    @forelse ($contracts as $contract)
        <article class="card tender">
            <div class="tags">
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
            <div class="between">
                <span style="font-size:12px;color:var(--accent);font-weight:500">
                    {{ $contract->sections->map(fn ($s) => $s->shortLabel())->implode(' · ') }}
                </span>
                <a class="button dark" href="{{ route('tenders.show', $contract) }}">Открыть</a>
            </div>
        </article>
    @empty
        <div class="card empty">
            <h2>Новых тендеров нет</h2>
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
