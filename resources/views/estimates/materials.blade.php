@extends('layouts.app')

@section('title', 'Лоток и комплектующие')

@php
    $qty = fn (int $milli): string => rtrim(rtrim(number_format($milli / 1000, 3, ',', ' '), '0'), ',');
@endphp

@section('top')
    <div class="top-row" style="justify-content:flex-start;gap:4px">
        <a class="back" href="{{ route('tenders.show', $contract) }}" aria-label="Назад к тендеру">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <div>
            <h1 style="font-size:17px;font-weight:600">Лоток и комплектующие</h1>
            <p class="sub" style="font-size:12px">По смете заказчика: количество и цены сметы, без НДС</p>
        </div>
    </div>
@endsection

@section('content')
    <section class="card">
        <h2 style="line-height:1.35;font-size:15px">{{ $contract->subject }}</h2>
        <p class="muted">{{ $contract->region->name }} · {{ $contract->company->name }}</p>

        @if ($report === null || $report->normativeFiles === 0)
            <p class="muted">Сметы заказчика в документации нет — выбирать не из чего. Лоток по проекту (без цен) — следующий этап.</p>
        @elseif ($report->groups === [])
            <p class="muted">В смете заказчика лотка и комплектующих не нашлось.</p>
        @else
            <div class="grid2">
                <div class="kv"><span class="label">Лоток и комплектующие</span><b style="font-size:20px">{{ $money->format($report->trayKopecks()) }}</b></div>
                <div class="kv"><span class="label">Все материалы и оборудование</span><b>{{ $money->format($report->materialsKopecks) }}</b></div>
            </div>
        @endif

        @if ($report !== null && $report->coveragePermille() !== null && $report->materialsCount > 0)
            <p class="muted" style="font-size:12px">Сверка: строки материалов дают {{ $money->format($report->materialsKopecks) }} из {{ $money->format($report->normativeKopecks) }} по итогам смет ({{ intdiv($report->coveragePermille(), 10) }},{{ $report->coveragePermille() % 10 }} %).@if ($report->coveragePermille() < 950) Часть строк не прочиталась — например, у распознанных сканов читаются только страницы с итогами.@endif</p>
        @endif

        @if ($report !== null && $report->filesToReread > 0)
            <form method="post" action="{{ route('estimates.materials.reread', $contract) }}" class="row">
                @csrf
                <p class="muted" style="margin:0">Смет, прочитанных до выборки материалов: {{ $report->filesToReread }}.</p>
                <button class="button secondary small" type="submit">Перечитать сметы</button>
            </form>
        @endif
    </section>

    @if ($report !== null)
        @foreach ($report->groups as $group)
            <section class="card">
                <h3 class="section-title between"><span>{{ $group['group']->label() }}</span><span>{{ $money->format($group['kopecks']) }}</span></h3>
                <ul class="work-list">
                    @foreach ($group['rows'] as $row)
                        <li>
                            <span class="work-name">{{ $row->name }}</span>
                            <span class="between muted">
                                <span>{{ $qty($row->quantity_milli) }} {{ $row->unit }}@if ($row->unitPriceKopecks() !== null) × {{ $money->format($row->unitPriceKopecks()) }}@endif</span>
                                <b style="color:var(--ink)">{{ $money->format($row->total_kopecks) }}</b>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
@endsection
