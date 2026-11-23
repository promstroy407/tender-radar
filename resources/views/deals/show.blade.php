@extends('layouts.app')

@section('title', 'Сделка')

@section('top')
    <div class="top-row" style="justify-content:flex-start;gap:4px">
        <a class="back" href="{{ route('deals', ['status' => $deal->status->value]) }}" aria-label="Назад к сделкам">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1 style="font-size:17px;font-weight:600">Сделка</h1>
    </div>
@endsection

@section('content')
    <section class="card">
        <div class="tags">
            <span class="badge {{ $deal->status->value }}">{{ $deal->status->label() }}</span>
            <span class="badge">{{ $deal->source->label() }}</span>
            @if ($deal->external_id)
                <span class="badge">{{ $deal->external_id }}</span>
            @endif
        </div>
        <h2 style="font-size:17px;line-height:1.35">{{ $deal->title() }}</h2>

        @if ($deal->contract)
            <p class="muted" style="color:var(--ink2)">{{ $deal->contract->subject }}, {{ $deal->contract->region->name }} · {{ $money->format($deal->contract->amount_kopecks) }}</p>
            <a href="{{ route('tenders.show', $deal->contract) }}" style="font-size:13px;font-weight:600">Карточка тендера →</a>
        @endif

        <div class="contacts" style="display:flex;flex-direction:column;gap:8px">
            @php($phone = $deal->phone ?? $deal->company?->phone)
            @php($email = $deal->email ?? $deal->company?->email)
            @if ($deal->name)<div><span>Имя</span><span>{{ $deal->name }}</span></div>@endif
            <div><span>Телефон</span><span>@if ($phone)<a href="tel:{{ $phone }}">{{ $phone }}</a>@else нет @endif</span></div>
            <div><span>Почта</span><span>@if ($email)<a href="mailto:{{ $email }}">{{ $email }}</a>@else нет @endif</span></div>
            @if ($deal->facility)<div><span>Объект</span><span>{{ $deal->facility }}</span></div>@endif
            @if ($deal->service)<div><span>Услуга</span><span>{{ $deal->service }}</span></div>@endif
            @if ($deal->origin)<div><span>Откуда узнали</span><span>{{ $deal->origin }}</span></div>@endif
            @if ($deal->estimate)<div><span>Калькулятор</span><span>{{ $deal->estimate }}</span></div>@endif
            @if ($deal->received_at)<div><span>Получена</span><span>{{ $deal->received_at->timezone(config('app.timezone'))->translatedFormat('j F Y, H:i') }}</span></div>@endif
            @if ($deal->offer)<div><span>КП отправлено</span><span>{{ $deal->offer->channel->label() }}, {{ $deal->offer->sent_at->translatedFormat('j F') }}</span></div>@endif
        </div>
        @if ($deal->message)
            <p style="margin:0;white-space:pre-line;font-size:14px">{{ $deal->message }}</p>
        @endif
        @if ($deal->source_url)
            <p class="muted" style="word-break:break-all">Страница: {{ $deal->source_url }}</p>
        @endif
    </section>

    <section class="card">
        <h3 class="section-title">Статус</h3>
        <form method="post" action="{{ route('deals.status', $deal) }}" class="status-grid">
            @csrf
            @method('put')
            @foreach ($statuses as $option)
                <button type="submit" name="status" value="{{ $option->value }}" class="secondary" aria-pressed="{{ $option === $deal->status ? 'true' : 'false' }}">{{ $option->label() }}</button>
            @endforeach
        </form>
        @error('status') <p class="error">{{ $message }}</p> @enderror
    </section>

    <form method="post" action="{{ route('deals.update', $deal) }}" class="card">
        @csrf
        @method('put')
        <h3 class="section-title">Суммы, ₽</h3>
        <label>Сумма КП
            <input type="text" name="offer_amount" inputmode="decimal" value="{{ old('offer_amount', $money->input($deal->offer_amount_kopecks)) }}">
        </label>
        @error('offer_amount') <p class="error">{{ $message }}</p> @enderror
        <label>Сумма договора
            <input type="text" name="contract_amount" inputmode="decimal" value="{{ old('contract_amount', $money->input($deal->contract_amount_kopecks)) }}">
        </label>
        @error('contract_amount') <p class="error">{{ $message }}</p> @enderror
        <label>Оплачено
            <input type="text" name="paid" inputmode="decimal" value="{{ old('paid', $money->input($deal->paid_kopecks)) }}">
        </label>
        @error('paid') <p class="error">{{ $message }}</p> @enderror
        @if ($deal->remainderKopecks() !== null)
            <p class="muted">Остаток к оплате: <b>{{ $money->format($deal->remainderKopecks()) }}</b></p>
        @endif
        <label>Напомнить
            <input type="date" name="remind_on" value="{{ old('remind_on', $deal->remind_on?->toDateString()) }}">
        </label>
        @error('remind_on') <p class="error">{{ $message }}</p> @enderror
        <label>Заметка
            <textarea name="note" rows="3" maxlength="2000" placeholder="Например: созвониться, отправить смету">{{ old('note', $deal->note) }}</textarea>
        </label>
        @error('note') <p class="error">{{ $message }}</p> @enderror
        <button type="submit">Сохранить</button>
    </form>

    <form method="post" action="{{ route('deals.destroy', $deal) }}" class="card" onsubmit="return confirm('Удалить сделку безвозвратно?')">
        @csrf
        @method('delete')
        <p class="muted">Для тестовых и спам-заявок. Удаление безвозвратное.</p>
        <button type="submit" class="secondary">Удалить сделку</button>
    </form>
@endsection
