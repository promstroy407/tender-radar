@extends('layouts.app')

@section('title', 'В работе')

@section('top')
    <div>
        <h1>В работе</h1>
        <p class="sub">Кому отправлены КП и что дальше</p>
    </div>
    <nav style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px" aria-label="Статусы">
        @foreach ($statuses as $item)
            <a class="stat {{ $item === $status ? 'active' : '' }}" href="{{ route('work', ['status' => $item->value]) }}"
               @if ($item === $status) aria-current="page" @endif>
                <b>{{ $counts[$item->value] }}</b><span>{{ $item->label() }}</span>
            </a>
        @endforeach
    </nav>
@endsection

@section('content')
    @forelse ($items as $offer)
        @php($contract = $offer->contract)
        <article class="card">
            <div class="between">
                <a href="{{ route('tenders.show', $contract) }}" style="font-size:15px;font-weight:600;color:var(--ink)">{{ $contract->company->name }}</a>
                <span class="badge {{ $offer->status->value }}" style="flex:none">{{ $offer->status->label() }}</span>
            </div>
            <p class="muted" style="color:var(--ink2);line-height:1.4">{{ $contract->subject }}, {{ $contract->region->name }} · {{ $money->format($contract->amount_kopecks) }}</p>
            <div class="between muted" style="font-size:12px">
                <span>
                    @if ($offer->sent_at)
                        Отправлено {{ $offer->sent_at->translatedFormat('j F') }}{{ $offer->channel ? ' · '.$offer->channel->label() : '' }}
                    @endif
                </span>
                <span style="font-weight:600;color:var(--ink2)">
                    @if ($offer->remind_on)
                        Напомнить {{ $offer->remind_on->translatedFormat('j F') }}
                    @elseif ($offer->note)
                        {{ \Illuminate\Support\Str::limit($offer->note, 40) }}
                    @endif
                </span>
            </div>
            <details>
                <summary style="font-size:13px;color:var(--accent)">Изменить статус</summary>
                <form method="post" action="{{ route('offers.update', $offer) }}" style="display:flex;flex-direction:column;gap:10px">
                    @csrf
                    @method('put')
                    <label>Статус
                        <select name="status">
                            @foreach (\App\Enums\OfferStatus::cases() as $option)
                                <option value="{{ $option->value }}" @selected($option === $offer->status)>{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Напомнить
                        <input type="date" name="remind_on" value="{{ $offer->remind_on?->toDateString() }}">
                    </label>
                    <label>Заметка
                        <textarea name="note" rows="2" maxlength="2000" placeholder="Например: созвониться, отправить смету">{{ $offer->note }}</textarea>
                    </label>
                    <button type="submit">Сохранить</button>
                </form>
            </details>
        </article>
    @empty
        <div class="card empty">
            <h2>Здесь пока пусто</h2>
            <p class="muted">Отправленные КП появятся здесь. Откройте тендер и нажмите «Отправить КП».</p>
        </div>
    @endforelse

    @error('status') <p class="error">{{ $message }}</p> @enderror
    @error('remind_on') <p class="error">{{ $message }}</p> @enderror
    @error('note') <p class="error">{{ $message }}</p> @enderror
@endsection
