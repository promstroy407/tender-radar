@extends('layouts.app')

@section('title', 'Калькулятор')

@section('top')
    <div class="top-row">
        <div>
            <h1>Калькулятор</h1>
            <p class="sub">Сторонняя смета: работы по смете заказчика и по нашим расценкам</p>
        </div>
    </div>
@endsection

@section('content')
    <section class="card">
        <h3 class="section-title">Новый расчёт</h3>
        <form method="post" action="{{ route('calculator.store') }}" style="display:flex;flex-direction:column;gap:8px">
            @csrf
            <label>Название
                <input type="text" name="name" value="{{ old('name') }}" maxlength="255" required placeholder="Например: Школа № 5, АПС">
            </label>
            @error('name') <p class="error">{{ $message }}</p> @enderror
            <label>Регион — по его ставкам считаются работы
                <select name="region_code" required>
                    @foreach ($regions as $region)
                        <option value="{{ $region->code }}" @selected(old('region_code') === $region->code)>{{ $region->name }}</option>
                    @endforeach
                </select>
            </label>
            @error('region_code') <p class="error">{{ $message }}</p> @enderror
            <button type="submit">Создать и загрузить смету</button>
        </form>
    </section>

    @forelse ($calculations as $calculation)
        @php($summary = $summaries[$calculation->id])
        <article class="card">
            <h2 style="font-size:15px;font-weight:600;line-height:1.35">{{ $calculation->name }}</h2>
            <p class="muted">{{ $calculation->region?->name }} · файлов: {{ $calculation->files->count() }} · {{ $calculation->created_at->format('d.m.Y') }}</p>
            @if ($summary->hasData())
                <div class="grid2">
                    @if ($summary->fromCustomerEstimate())
                        <div class="kv"><span class="label">По смете заказчика</span><b>{{ $money->format($summary->normativeKopecks) }}</b></div>
                    @endif
                    <div class="kv"><span class="label">По нашим расценкам</span><b>{{ $summary->linesPriced > 0 ? $money->format($summary->priceListKopecks) : '—' }}</b></div>
                </div>
            @endif
            <div class="between">
                <form method="post" action="{{ route('calculator.destroy', $calculation) }}" onsubmit="return confirm('Удалить расчёт и его файлы?')">
                    @csrf
                    @method('delete')
                    <button type="submit" class="secondary small">Удалить</button>
                </form>
                <a class="button dark" href="{{ route('calculator.show', $calculation) }}">Открыть</a>
            </div>
        </article>
    @empty
        <div class="card empty">
            <p class="muted">Расчётов пока нет. Создайте первый и загрузите смету — Excel, CSV или PDF.</p>
        </div>
    @endforelse
@endsection
