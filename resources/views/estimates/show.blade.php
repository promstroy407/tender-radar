@extends('layouts.app')

@section('title', 'Смета')

@section('top')
    <div class="top-row" style="justify-content:flex-start;gap:4px">
        <a class="back" href="{{ route('tenders.show', $contract) }}" aria-label="Назад к тендеру">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <div>
            <h1 style="font-size:17px;font-weight:600">Наш просчёт</h1>
            <p class="sub" style="font-size:12px">Для себя: только работы, без материалов</p>
        </div>
    </div>
@endsection

@section('content')
    <section class="card">
        <h2 style="line-height:1.35;font-size:15px">{{ $contract->subject }}</h2>
        <p class="muted">{{ $contract->region->name }} · {{ $contract->company->name }}</p>

        @if ($summary && $summary->hasData())
            <div class="grid2">
                <div class="kv"><span class="label">Работы — наш просчёт</span><b style="font-size:20px">{{ $money->format($summary->workKopecks()) }}</b></div>
                <div class="kv"><span class="label">Сумма контракта</span><b>{{ $money->format($summary->contractKopecks) }}</b></div>
            </div>
            @if ($summary->shareLabel())
                <p class="muted" style="color:var(--ink2)">Работы — <b>{{ $summary->shareLabel() }}</b> от суммы контракта (в контракте ещё материалы и оборудование).</p>
            @endif
            @if ($summary->fromCustomerEstimate())
                <p class="muted">Взято из сметы заказчика: итог без материалов, оборудования и НДС.@if ($summary->priceListKopecks > 0) Для сравнения, по нашему прайсу посчитано {{ $summary->linesPriced }} из {{ $summary->linesTotal - $summary->linesSkipped }} позиций проекта — {{ $money->format($summary->priceListKopecks) }}.@endif</p>
                <p><a href="{{ route('estimates.materials', $contract) }}" style="font-size:13px;font-weight:600">Лоток и комплектующие по смете заказчика →</a></p>
            @else
                <p class="muted">Посчитано по проекту и ведомостям: {{ $summary->linesPriced }} работ по нормам ГЭСН и ставкам региона «{{ $contract->region->name }}», с накладными и прибылью, без материалов.</p>
            @endif
            @if ($summary->linesUnmatched + $summary->linesNoPrice > 0)
                <p class="muted" style="color:var(--hotink)">Не посчитано позиций: {{ $summary->linesUnmatched + $summary->linesNoPrice }} — работ, которых нет в нашем прайсе. Список — ниже, в «Не посчитано».</p>
            @endif
        @elseif ($pending)
            <p class="muted">Документация читается — просчёт появится через минуту-две.</p>
        @else
            <p class="muted">Просчёта пока нет: в документации не нашлось сметы, ведомости или проекта, которые читаются (Excel, CSV, PDF с текстом). Сканы и чертежи — следующий этап.</p>
        @endif
    </section>

    @if ($summary && $summary->sections !== [])
        <section class="card">
            <h3 class="section-title">По разделам</h3>
            @php($maxSection = max(array_map(fn (array $row): int => $row['kopecks'], $summary->sections)))
            @foreach ($summary->sections as $row)
                <div style="display:flex;flex-direction:column;gap:4px">
                    <div class="between"><span>{{ $row['section']->label() }} <span class="muted">· {{ $row['lines'] }} поз.</span></span><b>{{ $money->format($row['kopecks']) }}</b></div>
                    <div class="bar"><i style="width:{{ $maxSection > 0 ? intdiv($row['kopecks'] * 100, $maxSection) : 0 }}%"></i></div>
                </div>
            @endforeach
        </section>
    @endif

    @if ($summary && $summary->topLines !== [])
        <section class="card">
            <h3 class="section-title">Основные работы</h3>
            <ul class="work-list">
                @foreach ($summary->topLines as $row)
                    <li>
                        <span class="work-name">{{ $row['name'] }}</span>
                        <span class="between muted"><span>{{ rtrim(rtrim(number_format($row['quantity_milli'] / 1000, 3, ',', ' '), '0'), ',') }} {{ $row['unit'] }}</span><b style="color:var(--ink)">{{ $money->format($row['kopecks']) }}</b></span>
                    </li>
                @endforeach
            </ul>
            <p class="muted" style="font-size:12px">Это прикидка для себя, не смета для отправки. Материалы считаются отдельно.</p>
        </section>
    @endif

    @if ($summary && $summary->unpriced !== [])
        <section class="card" id="unpriced">
            <h3 class="section-title">Не посчитано ({{ count($summary->unpriced) }})</h3>
            <p class="muted" style="font-size:12px">Этих работ нет в нашем прайсе или у них нет цены — в «Работы» они не вошли. Нажмите «+ в прайс»: форма заполнится по строке, впишите цену — смета пересчитается. Мусор из PDF («экспликация», номера листов) пропускайте.</p>
            <ul class="work-list">
                @foreach (array_slice($summary->unpriced, 0, 150) as $row)
                    <li class="unpriced">
                        <span class="work-name">{{ $row['name'] }}</span>
                        <span class="between muted">
                            <span>{{ rtrim(rtrim(number_format($row['quantity_milli'] / 1000, 3, ',', ' '), '0'), ',') }} {{ $row['unit'] }}</span>
                            @if ($row['price_item'])
                                <a href="{{ route('prices') }}#item-{{ $row['price_item']->id }}" style="font-weight:600">нет цены: «{{ \Illuminate\Support\Str::limit($row['price_item']->name, 30) }}» →</a>
                            @else
                                <a href="{{ route('prices.create', ['name' => $row['name'], 'unit' => $row['unit'], 'back' => $contract->id]) }}" style="font-weight:600">+ в прайс</a>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
            @if (count($summary->unpriced) > 150)
                <p class="muted">И ещё {{ count($summary->unpriced) - 150 }} — в «Все позиции».</p>
            @endif
        </section>
    @endif

    <details class="card" @if (! $estimate || $estimate->files->isEmpty()) open @endif>
        <summary>Файлы документации ({{ $estimate?->files->count() ?? 0 }})</summary>
        @if ($estimate && $estimate->files->isNotEmpty())
            <div style="display:flex;flex-direction:column;gap:10px">
                @foreach ($estimate->files as $file)
                    <div style="border-top:1px solid var(--chip);padding-top:8px;display:flex;flex-direction:column;gap:6px">
                        <div class="between">
                            <span style="font-size:13px;word-break:break-word">{{ $file->original_name }}</span>
                            <span class="badge {{ $file->status->value === 'parsed' ? 'on' : ($file->status->value === 'failed' ? 'deal' : 'off') }}" style="flex:none">{{ $file->status->label() }}</span>
                        </div>
                        @if ($file->error)
                            <p class="muted" style="color:var(--hotink);font-size:12px">{{ $file->error }}</p>
                        @endif
                        @if ($totals = $file->normativeTotals())
                            <p class="muted" style="font-size:12px">Смета заказчика: итог {{ $money->format($totals->totalKopecks) }}, материалы {{ $money->format($totals->materialsKopecks) }}@if ($totals->equipmentKopecks), оборудование {{ $money->format($totals->equipmentKopecks) }}@endif → <b style="color:var(--ink)">работы {{ $money->format($totals->worksKopecks()) }}</b></p>
                        @endif
                        <div class="row">
                            @if ($file->path !== '' && $file->extension() === 'pdf')
                                <a class="button secondary small" href="{{ route('estimates.files.text', $file) }}" target="_blank">Текст</a>
                            @endif
                            @if ($file->path !== '')
                                <form method="post" action="{{ route('estimates.files.reparse', $file) }}">
                                    @csrf
                                    <button type="submit" class="secondary small">Прочитать заново</button>
                                </form>
                            @endif
                            <form method="post" action="{{ route('estimates.files.destroy', $file) }}">
                                @csrf
                                @method('delete')
                                <button type="submit" class="secondary small">Удалить</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ route('estimates.upload', $contract) }}" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:8px;margin-top:12px">
            @csrf
            <label>Добавить свои файлы (до 20 за раз, до 64 МБ каждый)
                <input type="file" name="files[]" multiple required
                       accept=".xlsx,.xls,.xlsm,.ods,.csv,.pdf,.zip,.rar,.7z,.gsfx,.gsf,.xml,.doc,.docx,.dwg,.dxf,.jpg,.jpeg,.png,.tif,.tiff">
            </label>
            @error('files') <p class="error">{{ $message }}</p> @enderror
            @error('files.*') <p class="error">{{ $message }}</p> @enderror
            <button type="submit" class="secondary">Загрузить и посчитать</button>
            <p class="muted" style="font-size:12px">Читаются Excel, CSV и PDF с текстом. ГРАНД-Смету выгрузите в Excel. Сканы и чертежи — следующий этап.</p>
        </form>
        <div class="row" style="margin-top:8px">
            <a class="button secondary small" href="{{ route('estimates.export', $contract) }}">Скачать в Excel</a>
            <a class="button secondary small" href="{{ route('prices') }}">Прайс работ</a>
        </div>
    </details>

    @if ($lines && ($lines->total() > 0 || $attention))
        <details class="card" @if ($attention || request()->has('page')) open @endif>
        <summary>Все позиции и ручная правка ({{ $summary?->linesTotal ?? 0 }})</summary>
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
        </details>
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
