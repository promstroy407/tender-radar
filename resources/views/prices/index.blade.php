@extends('layouts.app')

@section('title', 'Прайс работ')

@section('top')
    <div class="top-row" style="justify-content:flex-start;gap:4px">
        <a class="back" href="{{ route('settings') }}" aria-label="Назад к настройкам">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <div>
            <h1 style="font-size:17px;font-weight:600">Прайс работ</h1>
            <p class="sub" style="font-size:12px">По нему считается быстрая смета тендера</p>
        </div>
    </div>
@endsection

@php
    $rubles = fn (?int $kopecks): string => $kopecks === null ? '' : (intdiv($kopecks, 100).($kopecks % 100 ? ','.str_pad((string) ($kopecks % 100), 2, '0', STR_PAD_LEFT) : ''));
@endphp

@section('content')
    <div class="card">
        <p class="muted">Цена работы считается по норме ГЭСН: затраты труда × сметная цена часа рабочего в регионе тендера (ФГИС ЦС) + накладные расходы и сметная прибыль. Без машин (≈2 % в электромонтаже) и материалов. Нормы подобраны начерно — проверьте. Если нормы нет, используется цена, вписанная вручную.</p>
        <p class="muted"><b>Ключевые слова</b> — через запятую. Каждая фраза — слова, которые все должны быть в строке сметы (можно начало слова: «извещател дым»). Побеждает самая подробная фраза.</p>
    </div>

    @if ($errors->any())
        <div class="flash err" role="alert">{{ $errors->first() }}</div>
    @endif

    @foreach ($sections as $section)
        <h2 class="section-title" style="margin-top:8px">{{ $section->label() }}</h2>
        @foreach ($groups[$section->value] ?? [] as $item)
            <details class="card" id="item-{{ $item->id }}" style="gap:6px">
                <summary style="justify-content:space-between;gap:8px">
                    <span style="font-size:14px;{{ $item->is_active ? '' : 'color:var(--muted);text-decoration:line-through' }}">{{ $item->name }}</span>
                    @php($regionPrices = array_filter($normative[$item->id] ?? []))
                    <span style="flex:none;font-size:13px;{{ $regionPrices === [] && $item->work_price_kopecks === null ? 'color:var(--hot)' : '' }}">
                        @if ($regionPrices !== [])
                            ГЭСН {{ $item->gesn_code }}
                        @elseif ($item->work_price_kopecks !== null)
                            {{ $rubles($item->work_price_kopecks) }} ₽/{{ $item->unit }}
                        @elseif ($item->gesn_code)
                            ГЭСН {{ $item->gesn_code }} — ждём загрузку
                        @else
                            нет цены
                        @endif
                    </span>
                </summary>
                @if ($regionPrices !== [])
                    <div style="display:flex;flex-direction:column;gap:4px;font-size:13px">
                        @foreach ($regions as $region)
                            @if ($price = $normative[$item->id][$region->code] ?? null)
                                <div class="between"><span class="muted">{{ $region->name }}</span><b>{{ $money->format($price->kopecksPerUnit) }}/{{ $item->unit }}</b></div>
                            @endif
                        @endforeach
                        @php($any = reset($regionPrices))
                        <p class="muted" style="font-size:12px">НР {{ $any->overheadPercent }} %, СП {{ $any->profitPercent }} % от оплаты труда; ставки за {{ $any->period }}.</p>
                    </div>
                @endif
                <form method="post" action="{{ route('prices.update', $item) }}" style="display:flex;flex-direction:column;gap:10px">
                    @csrf
                    @method('put')
                    @include('prices.fields', ['item' => $item])
                    <button type="submit">Сохранить</button>
                </form>
            </details>
        @endforeach
    @endforeach

    <details class="card">
        <summary>+ Добавить позицию</summary>
        <form method="post" action="{{ route('prices.store') }}" style="display:flex;flex-direction:column;gap:10px">
            @csrf
            @include('prices.fields', ['item' => null])
            <button type="submit">Добавить</button>
        </form>
    </details>
@endsection
