@extends('layouts.app')

@section('title', 'Новая позиция прайса')

@section('top')
    <div class="top-row" style="justify-content:flex-start;gap:4px">
        <a class="back" href="{{ $back ? route('estimates.show', $back).'#unpriced' : route('prices') }}" aria-label="Назад">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <div>
            <h1 style="font-size:17px;font-weight:600">Новая позиция прайса</h1>
            <p class="sub" style="font-size:12px">Заполнено по строке сметы — проверьте и сохраните</p>
        </div>
    </div>
@endsection

@section('content')
    @if ($errors->any())
        <div class="flash err" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('prices.store') }}" class="card" style="display:flex;flex-direction:column;gap:10px">
        @csrf
        @if ($back)
            <input type="hidden" name="back" value="{{ $back }}">
        @endif
        <label>Раздел
            <select name="section">
                @foreach ($sections as $section)
                    <option value="{{ $section->value }}" @selected(old('section', $draft->section->value) === $section->value)>{{ $section->label() }}</option>
                @endforeach
            </select>
        </label>
        <label>Работа
            <input type="text" name="name" value="{{ old('name', $draft->name) }}" maxlength="255" required>
        </label>
        <div class="grid2">
            <label>Цена, ₽ за единицу
                <input type="text" name="price_rubles" value="{{ old('price_rubles') }}" inputmode="decimal" placeholder="например, 350" autofocus>
            </label>
            <label>Единица
                <select name="unit">
                    @foreach ($units as $unit)
                        <option value="{{ $unit }}" @selected(old('unit', $draft->unit) === $unit)>{{ $unit }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <label>Норма ГЭСН (шифр), если знаете — тогда цена по региону тендера
            <input type="text" name="gesn_code" value="{{ old('gesn_code') }}" maxlength="20" placeholder="например, 10-08-001-04" inputmode="numeric">
        </label>
        <label>Ключевые слова — по ним строки смет находят эту работу
            <textarea name="keywords" rows="2" maxlength="2000" required>{{ old('keywords', $draft->keywords) }}</textarea>
        </label>
        <p class="muted" style="font-size:12px">Через запятую можно дать несколько вариантов. Начала слов: «извещат дым» найдёт «Извещатель пожарный дымовой».</p>
        <input type="hidden" name="is_active" value="1">
        <button type="submit">Добавить в прайс и пересчитать</button>
    </form>
@endsection
