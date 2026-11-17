@extends('layouts.app')

@section('title', 'Воронка')

@section('top')
    <div class="top-row" style="justify-content:flex-start;gap:4px">
        <a class="back" href="{{ route('deals') }}" aria-label="Назад к сделкам">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1 style="font-size:17px;font-weight:600">Воронка</h1>
    </div>
    <nav class="chips" aria-label="Источник">
        <a class="chip {{ $source === null ? 'active' : '' }}" href="{{ route('funnel') }}">Все</a>
        @foreach ($sources as $item)
            <a class="chip {{ $item === $source ? 'active' : '' }}" href="{{ route('funnel', ['source' => $item->value]) }}">{{ $item->label() }}</a>
        @endforeach
    </nav>
@endsection

@section('content')
    <section class="card">
        <div class="between">
            <h2 class="section-title">Этапы</h2>
            <span class="muted">Всего сделок: <b style="color:var(--ink)">{{ $report->total }}</b></span>
        </div>
        <table class="report">
            <thead>
                <tr><th>Этап</th><th class="num">Дошли</th><th class="num">От пред.</th><th class="num">От всех</th></tr>
            </thead>
            <tbody>
                @foreach ($report->stages as $row)
                    <tr>
                        <td>
                            <a href="{{ route('deals', array_filter(['status' => $row['status']->value, 'source' => $source?->value])) }}" style="color:var(--ink)">{{ $row['status']->label() }}</a>
                            <div class="bar" style="margin-top:4px"><i style="width:{{ intdiv($row['fromAll'] ?? 0, 10) }}%"></i></div>
                            <span class="muted" style="font-size:11px">сейчас: {{ $row['now'] }}</span>
                        </td>
                        <td class="num"><b>{{ $row['reached'] }}</b></td>
                        <td class="num">{{ $money->percent($row['fromPrevious']) }}</td>
                        <td class="num">{{ $money->percent($row['fromAll']) }}</td>
                    </tr>
                @endforeach
                @foreach ($report->exits as $row)
                    <tr>
                        <td><a href="{{ route('deals', array_filter(['status' => $row['status']->value, 'source' => $source?->value])) }}" style="color:var(--muted)">{{ $row['status']->label() }}</a></td>
                        <td class="num">{{ $row['count'] }}</td>
                        <td class="num"></td>
                        <td class="num"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="muted" style="font-size:12px">«Дошли» — сделки, которые были на этом этапе или прошли дальше, включая потом отказавшихся.</p>
    </section>

    <section class="card">
        <h2 class="section-title">Деньги</h2>
        <div class="grid2">
            <div class="kv"><span class="label">Сумма КП</span><b>{{ $money->format($report->offerKopecks) }}</b></div>
            <div class="kv"><span class="label">Сумма договоров</span><b>{{ $money->format($report->contractKopecks) }}</b></div>
            <div class="kv"><span class="label">Оплачено</span><b>{{ $money->format($report->paidKopecks) }}</b></div>
            <div class="kv"><span class="label">Остаток по договорам</span><b>{{ $money->format($report->remainderKopecks) }}</b></div>
        </div>
    </section>

    <section class="card">
        <h2 class="section-title">Источники</h2>
        <table class="report">
            <thead>
                <tr><th>Источник</th><th class="num">Сделок</th><th class="num">Договоров</th><th class="num">Сумма договоров</th></tr>
            </thead>
            <tbody>
                @foreach ($report->sources as $row)
                    <tr>
                        <td>{{ $row['source']->label() }}</td>
                        <td class="num">{{ $row['deals'] }}</td>
                        <td class="num">{{ $row['contracts'] }}</td>
                        <td class="num">{{ $money->format($row['contractKopecks']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endsection
