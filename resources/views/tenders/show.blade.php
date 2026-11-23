@extends('layouts.app')

@section('title', 'Карточка тендера')

@section('top')
    <div class="top-row" style="justify-content:flex-start;gap:4px">
        <a class="back" href="{{ route('home') }}" aria-label="Назад к списку">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1 style="font-size:17px;font-weight:600">Карточка тендера</h1>
    </div>
@endsection

@php
    $company = $contract->company;
    $offer = $contract->offer;
    $deal = $contract->deal;
@endphp

@section('main_class', 'with-actions')

@section('content')
    <section class="card">
        <div class="tags">
            @if ($contract->isHot())
                <span class="badge hot">ГОРЯЧИЙ · СУБПОДРЯД СМП</span>
            @endif
            <span class="badge">{{ $contract->law->label() }}</span>
            <span class="badge">{{ $contract->region->name }}</span>
            <span class="badge">{{ $contract->kind->label() }}</span>
        </div>
        <h2 style="font-size:17px;line-height:1.35">{{ $contract->subject }}</h2>
        <div class="grid2">
            <div class="kv"><span class="label">Цена контракта</span><b style="font-size:16px;font-weight:700">{{ $money->format($contract->amount_kopecks) }}</b></div>
            <div class="kv"><span class="label">Срок работ</span><b>{{ $contract->ends_at ? 'до '.$contract->ends_at->translatedFormat('j F Y') : 'не указан' }}</b></div>
            <div class="kv"><span class="label">Заказчик</span><span>{{ $contract->customer_name }}</span></div>
            <div class="kv"><span class="label">Контракт подписан</span><span>{{ $contract->signed_at->translatedFormat('j F Y') }}</span></div>
        </div>
        @if ($contract->requires_smp_subcontract)
            <div class="note-hot">Заказчик обязал победителя привлечь субподрядчиков из МСП (ч. 5 ст. 30 44-ФЗ): генподрядчику нужно кого-то нанять.</div>
        @endif
        @if ($contract->sections->isNotEmpty())
            <div style="display:flex;flex-direction:column;gap:6px">
                <span class="label">Найдено в документации</span>
                <div class="tags">
                    @foreach ($contract->sections as $section)
                        <span class="tag">{{ $section->label() }}</span>
                    @endforeach
                </div>
            </div>
        @endif
        <a href="{{ $eisUrl }}" target="_blank" rel="noopener" style="font-size:13px;font-weight:500;min-height:32px;display:inline-flex;align-items:center">Открыть контракт в ЕИС →</a>
    </section>

    <section class="card">
        <h3 class="section-title" style="text-transform:uppercase;letter-spacing:.5px">Победитель</h3>
        <div class="kv">
            <b style="font-size:16px">{{ $company->name }}</b>
            <span class="muted">ИНН {{ $company->inn }}@if ($company->city) · {{ $company->city }}@endif</span>
        </div>
        <div class="contacts" style="display:flex;flex-direction:column;gap:8px">
            <div><span>Почта</span><span>{{ $company->email ?? 'нет' }}</span></div>
            <div><span>Телефон</span><span>@if ($company->phone)<a href="tel:{{ $company->phone }}">{{ $company->phone }}</a>@else нет @endif</span></div>
            <div><span>Сайт</span><span>{{ $company->website ?? 'нет' }}</span></div>
            <div><span>Руководитель</span><span>{{ $company->director_name ?? 'нет' }}</span></div>
        </div>
        <p class="muted" style="font-size:12px">Источник контактов: карточка контракта в ЕИС.</p>
    </section>

    <section class="card">
        <div class="between">
            <h3 class="section-title" style="text-transform:uppercase;letter-spacing:.5px">Документация закупки</h3>
            @if ($contract->notification_number)
                <a href="https://zakupki.gov.ru/epz/order/extendedsearch/results.html?searchString={{ $contract->notification_number }}" target="_blank" rel="noopener" style="font-size:13px;font-weight:600">В ЕИС →</a>
            @endif
        </div>
        @if ($contract->documents->isNotEmpty())
            <ul class="docs">
                @foreach ($contract->documents as $document)
                    <li>
                        <a href="{{ $document->url }}" target="_blank" rel="noopener">{{ $document->name }}</a>
                        @if ($document->size_bytes)<span class="muted"> · {{ number_format($document->size_bytes / 1048576, 1, ',', ' ') }} МБ</span>@endif
                        @if ($document->description)<div class="muted" style="font-size:12px">{{ $document->description }}</div>@endif
                    </li>
                @endforeach
            </ul>
            <form method="post" action="{{ route('tenders.documents.estimate', $contract) }}">
                @csrf
                <button type="submit">Загрузить в смету и посчитать</button>
            </form>
        @elseif ($contract->documents_checked_at)
            <p class="muted">В извещении файлов не найдено ({{ $contract->documents_checked_at->translatedFormat('j F, H:i') }}).</p>
        @elseif ($contract->notification_number)
            <p class="muted">Документация ещё не загружена из ЕИС.</p>
        @else
            <p class="muted">У контракта нет номера извещения — документацию найти нельзя.</p>
        @endif
        @if ($contract->notification_number)
            <form method="post" action="{{ route('tenders.documents.fetch', $contract) }}">
                @csrf
                <button type="submit" class="secondary">{{ $contract->documents_checked_at ? 'Обновить список' : 'Найти документацию' }}</button>
            </form>
        @endif
    </section>

    <section class="card">
        <div class="between">
            <h3 class="section-title" style="text-transform:uppercase;letter-spacing:.5px">Смета — для себя</h3>
            <a href="{{ route('estimates.show', $contract) }}" style="font-size:13px;font-weight:600">{{ $estimateSummary && $estimateSummary->hasData() ? 'Открыть' : 'Посчитать' }} →</a>
        </div>
        @if ($estimateSummary && $estimateSummary->hasData())
            <div class="grid2">
                <div class="kv"><span class="label">Работы</span><b>{{ $money->format($estimateSummary->workKopecks()) }}</b></div>
                <div class="kv"><span class="label">От суммы контракта</span><b>{{ $estimateSummary->shareLabel() ?? '—' }}</b></div>
            </div>
            @if ($estimateSummary->linesUnmatched + $estimateSummary->linesNoPrice > 0)
                <p class="muted">Без цены: {{ $estimateSummary->linesUnmatched + $estimateSummary->linesNoPrice }} поз. — проверьте в смете.</p>
            @endif
        @else
            <p class="muted">Загрузите смету заказчика из документации — возьмём стоимость работ по нормативам, без материалов.</p>
        @endif
    </section>

    <details class="card">
        <summary>Текст письма для этого победителя</summary>
        <p class="letter">{{ $message }}</p>
    </details>
@endsection

@section('bottom')
    <div class="actionbar">
        <div>
            @if ($offer && $deal)
                <a role="status" class="flash ok" href="{{ route('deals.show', $deal) }}" style="display:block;text-align:center;font-weight:600">
                    КП отправлено ({{ $offer->channel->label() }}, {{ $offer->sent_at->translatedFormat('j F') }}). Сделка: «{{ $deal->status->label() }}» →
                </a>
            @else
                <form method="post" action="{{ route('offers.email', $contract) }}">
                    @csrf
                    <button type="submit" style="width:100%;min-height:48px" @disabled(! $mailReady || ! $company->email)>Отправить КП на почту</button>
                </form>
                @if (! $mailReady)
                    <p class="muted" style="text-align:center;font-size:12px">Почта kp@es12.ru ещё не подключена — пока через мессенджер.</p>
                @endif
            @endif
            <div class="grid3">
                <form method="post" action="{{ route('offers.messenger', $contract) }}" target="_blank" data-after-open>
                    @csrf
                    <input type="hidden" name="channel" value="whatsapp">
                    <button type="submit" class="secondary" style="width:100%;font-size:13px" @disabled(! $whatsappUrl)>WhatsApp</button>
                </form>
                <form method="post" action="{{ route('offers.messenger', $contract) }}" target="_blank" data-after-open data-copy="{{ $message }}">
                    @csrf
                    <input type="hidden" name="channel" value="telegram">
                    <button type="submit" class="secondary" style="width:100%;font-size:13px" @disabled(! $telegramUrl)>Telegram</button>
                </form>
                <form method="post" action="{{ route('offers.dismiss', $contract) }}">
                    @csrf
                    <button type="submit" class="secondary" style="width:100%;font-size:13px;color:var(--muted)" @disabled($deal !== null)>Не интересно</button>
                </form>
            </div>
            @if (! $company->phone)
                <p class="muted" style="text-align:center;font-size:12px">Нет мобильного номера — мессенджеры недоступны.</p>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Мессенджер открывается в новой вкладке; для Telegram текст письма копируется,
    // его остаётся вставить в чат. Эта страница обновляется, чтобы показать статус.
    document.querySelectorAll('form[data-after-open]').forEach((form) => {
        form.addEventListener('submit', () => {
            const text = form.dataset.copy;
            if (text && navigator.clipboard) {
                navigator.clipboard.writeText(text).catch(() => {});
            }
            setTimeout(() => location.reload(), 1500);
        });
    });
</script>
@endpush
