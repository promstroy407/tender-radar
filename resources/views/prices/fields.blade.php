@php
    $price = $item?->work_price_kopecks;
    $priceText = $price === null ? '' : intdiv($price, 100).($price % 100 ? ','.str_pad((string) ($price % 100), 2, '0', STR_PAD_LEFT) : '');
@endphp
<label>Раздел
    <select name="section">
        @foreach ($sections as $section)
            <option value="{{ $section->value }}" @selected($item?->section === $section)>{{ $section->label() }}</option>
        @endforeach
    </select>
</label>
<label>Работа
    <input type="text" name="name" value="{{ $item?->name }}" maxlength="255" required>
</label>
<div class="grid2">
    <label>Цена работы, ₽
        <input type="text" name="price_rubles" value="{{ $priceText }}" inputmode="decimal" placeholder="не задана">
    </label>
    <label>Единица
        <select name="unit">
            @foreach ($units as $unit)
                <option value="{{ $unit }}" @selected($item?->unit === $unit)>{{ $unit }}</option>
            @endforeach
        </select>
    </label>
</div>
<label>Ключевые слова
    <textarea name="keywords" rows="2" maxlength="2000" required>{{ $item?->keywords }}</textarea>
</label>
<input type="hidden" name="is_active" value="0">
<label class="check"><input type="checkbox" name="is_active" value="1" @checked($item === null || $item->is_active)> Использовать в расчёте</label>
