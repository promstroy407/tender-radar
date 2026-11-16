@extends('layouts.app')

@section('title', 'Смета')

@section('top')
    <div class="top-row" style="justify-content:flex-start;gap:4px">
        <a class="back" href="{{ route('tenders.show', $contract) }}" aria-label="Назад к тендеру">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <div>
            <h1 style="font-size:17px;font-weight:600">Быстрая смета</h1>
            <p class="sub" style="font-size:12px">Для себя: только работы по нашему прайсу</p>
        </div>
    </div>
@endsection

@section('content')
    <section class="card">
        <h2 style="line-height:1.35">{{ $contract->subject }}</h2>
        <p class="muted">{{ $contract->region->name }} · {{ $contract->company->name }}</p>

        @if ($summary && $summary->hasData())
            <div class="grid2">
                <div class="kv"><span class="label">Работы</span><b style="font-size:17px">{{ $money->format($summary->workKopecks()) }}</b></div>
                <div class="kv"><span class="label">Сумма контракта</span><b>{{ $money->format($summary->contractKopecks) }}</b></div>
            </div>
            @if ($summary->shareLabel())
                <p class="muted" style="color:var(--ink2)">Работы — <b>{{ $summary->shareLabel() }}</b> от суммы контракта (в ней есть и материалы).</p>
            @endif
            @if ($summary->normativeFiles > 0)
                <p class="muted">По нормативам из сметы заказчика: <b style="color:var(--ink)">{{ $money->format($summary->normativeKopecks) }}</b> — оплата труда, машины, накладные расходы и сметная прибыль, без материалов, оборудования и НДС.</p>
            @endif
            @if ($summary->linesTotal > 0)
                <p class="muted">
                    Позиции ведомостей: {{ $summary->linesTotal }} · посчитано по прайсу {{ $summary->linesPriced }} ({{ $money->format($summary->priceListKopecks) }})
                    @if ($summary->linesUnmatched) · <b style="color:var(--hot)">не сопоставлено {{ $summary->linesUnmatched }}</b>@endif
                    @if ($summary->linesNoPrice) · <b style="color:var(--hot)">без цены {{ $summary->linesNoPrice }}</b>@endif
                    @if ($summary->linesSkipped) · не считаем {{ $summary->linesSkipped }}@endif
                </p>
                <p class="muted" style="font-size:12px">Позиции ведомостей считаются по нормам ГЭСН и ставкам труда региона «{{ $contract->region->name }}» (ФГИС ЦС) с накладными расходами и сметной прибылью, без машин и материалов. Где у работы нет нормы — по цене из «Прайса работ».</p>
            @endif
            <p class="muted" style="font-size:12px">Материалы не считаем — они отдельно. Это прикидка, не смета для отправки.</p>
            <div class="row">
                <a class="button secondary" href="{{ route('estimates.export', $contract) }}">Скачать в Excel</a>
                <a class="button secondary" href="{{ route('prices') }}">Прайс работ</a>
            </div>
        @else
            <p class="muted">Загрузите документацию тендера: смету заказчика (Excel), ведомость работ или ZIP-архив. Из нормативной сметы приложение возьмёт стоимость работ без материалов.</p>
        @endif
    </section>

    <form method="post" action="{{ route('estimates.upload', $contract) }}" enctype="multipart/form-data" class="card">
        @csrf
        <h2 class="section-title">Файлы документации</h2>
        <label>Добавить файлы (до 20 за раз, до 64 МБ каждый)
            <input type="file" name="files[]" multiple required
                   accept=".xlsx,.xls,.xlsm,.ods,.csv,.pdf,.zip,.rar,.7z,.gsfx,.gsf,.xml,.doc,.docx,.dwg,.dxf,.jpg,.jpeg,.png,.tif,.tiff">
        </label>
        @error('files') <p class="error">{{ $message }}</p> @enderror
        @error('files.*') <p class="error">{{ $message }}</p> @enderror
        <button type="submit">Загрузить и посчитать</button>
        <p class="muted" style="font-size:12px">Читаются Excel, CSV и текстовые PDF. ГРАНД-Смету выгрузите в Excel. Чертежи и сканы — следующий этап.</p>

        @if ($estimate && $estimate->files->isNotEmpty())
            <div style="display:flex;flex-direction:column;gap:8px">
                @foreach ($estimate->files as $file)
                    <div style="border-top:1px solid var(--chip);padding-top:8px;display:flex;flex-direction:column;gap:6px">
                        <div class="between">
                            <span style="font-size:14px;word-break:break-word">{{ $file->original_name }}</span>
                            <span class="badge {{ $file->status->value === 'parsed' ? 'on' : ($file->status->value === 'failed' ? 'deal' : 'off') }}" style="flex:none">{{ $file->status->label() }}</span>
                        </div>
                        @if ($file->error)
                            <p class="muted" style="color:var(--hotink)">{{ $file->error }}</p>
                        @endif
                        @if ($totals = $file->normativeTotals())
                            <p class="muted">Нормативная смета: итог {{ $money->format($totals->totalKopecks) }}, материалы {{ $money->format($totals->materialsKopecks) }}@if ($totals->equipmentKopecks), оборудование {{ $money->format($totals->equipmentKopecks) }}@endif → <b style="color:var(--ink)">работы {{ $money->format($totals->worksKopecks()) }}</b></p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </form>

    @if ($estimate && $estimate->files->isNotEmpty())
        <section class="card">
            <h2 class="section-title">Управление файлами</h2>
            @foreach ($estimate->files as $file)
                <div class="between" style="gap:6px">
                    <span class="muted" style="word-break:break-word">{{ \Illuminate\Support\Str::limit($file->original_name, 50) }}</span>
                    <div class="row" style="flex:none">
                        @if ($file->path !== '')
                            <form method="post" action="{{ route('estimates.files.reparse', $file) }}">
                                @csrf
                                <button type="submit" class="secondary" style="min-height:36px;padding:0 10px;font-size:12px">Прочитать заново</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('estimates.files.destroy', $file) }}">
                            @csrf
                            @method('delete')
                            <button type="submit" class="secondary" style="min-height:36px;padding:0 10px;font-size:12px">Удалить</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    @if ($lines && ($lines->total() > 0 || $attention))
        <nav class="chips" style="margin:0" aria-label="Позиции">
            <a class="chip {{ $attention ? '' : 'active' }}" href="{{ route('estimates.show', $contract) }}">Все позиции</a>
            <a class="chip {{ $attention ? 'active' : '' }}" href="{{ route('estimates.show', ['contract' => $contract, 'filter' => 'attention']) }}">Требуют внимания</a>
        </nav>

        @forelse ($lines as $line)
            @php($work = $line->workKopecks($unitPrices[$line->price_item_id] ?? null))
            <article class="card" id="line-{{ $line->id }}" style="gap:8px">
                <p style="margin:0;font-size:14px;line-height:1.4">{{ $line->name }}</p>
                <div class="between muted">
                    <span>{{ rtrim(rtrim(number_format($line->quantity_milli / 1000, 3, ',', ' '), '0'), ',') }} {{ $line->unit }}</span>
                    <span>
                        @if ($line->match->value === 'skip')
                            не считаем
                        @elseif ($work !== null)
                            работа <b style="color:var(--ink)">{{ $money->format($work) }}</b>
                        @elseif ($line->priceItem)
                            <b style="color:var(--hot)">нет цены</b>
                        @else
                            <b style="color:var(--hot)">не сопоставлено</b>
                        @endif
                    </span>
                </div>
                <form method="post" action="{{ route('estimates.lines.update', $line) }}" class="row" style="flex-wrap:nowrap">
                    @csrf
                    @method('put')
                    <input type="hidden" name="filter" value="{{ $attention ? 'attention' : 'all' }}">
                    <input type="hidden" name="page" value="{{ $lines->currentPage() }}">
                    <select name="price_item" aria-label="Позиция прайса" style="flex:1;min-width:0;font-size:13px">
                        <option value="skip" @selected($line->match->value === 'skip')>— не считать —</option>
                        @if (! $line->priceItem && $line->match->value !== 'skip')
                            <option value="" selected disabled>выберите работу из прайса</option>
                        @endif
                        @foreach ($sections as $section)
                            @if ($priceGroups->has($section->value))
                                <optgroup label="{{ $section->label() }}">
                                    @foreach ($priceGroups[$section->value] as $item)
                                        <option value="{{ $item->id }}" @selected($line->price_item_id === $item->id && $line->match->value !== 'skip')>
                                            {{ $item->name }} ({{ ($unitPrices[$item->id] ?? null) !== null ? $money->format($unitPrices[$item->id]) : 'нет цены' }}/{{ $item->unit }})
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                    <button type="submit" class="secondary" style="flex:none;padding:0 12px">OK</button>
                </form>
            </article>
        @empty
            <div class="card empty"><p class="muted">Всё сопоставлено и посчитано.</p></div>
        @endforelse

        @if ($lines->hasPages())
            <div class="row" style="justify-content:center">
                @if ($lines->previousPageUrl())<a class="button secondary" href="{{ $lines->previousPageUrl() }}">← Назад</a>@endif
                <span class="muted" style="align-self:center">стр. {{ $lines->currentPage() }} из {{ $lines->lastPage() }}</span>
                @if ($lines->nextPageUrl())<a class="button secondary" href="{{ $lines->nextPageUrl() }}">Дальше →</a>@endif
            </div>
        @endif
    @endif
@endsection

@if ($pending)
    @push('scripts')
    <script>
        // Файлы ещё читаются — обновим страницу сами.
        setTimeout(() => location.reload(), 8000);
    </script>
    @endpush
@endif
