@extends('layouts.app')

@section('title', 'Сделки')

@section('top')
    <div class="between">
        <div>
            <h1>Сделки</h1>
            <p class="sub">Тендеры и заявки клиентов — одна воронка</p>
        </div>
        <a class="button secondary" href="{{ route('funnel', array_filter(['source' => $source?->value])) }}" style="min-height:40px;font-size:13px">Воронка</a>
    </div>
    <nav class="scroll-x" aria-label="Статусы">
        @foreach ($statuses as $item)
            <a class="stat {{ $item === $status ? 'active' : '' }}" href="{{ route('deals', array_filter(['status' => $item->value, 'source' => $source?->value])) }}"
               @if ($item === $status) aria-current="page" @endif>
                <b>{{ $counts[$item->value] }}</b><span>{{ $item->label() }}</span>
            </a>
        @endforeach
    </nav>
    <nav class="chips" aria-label="Источник">
        <a class="chip {{ $source === null ? 'active' : '' }}" href="{{ route('deals', ['status' => $status->value]) }}">Все</a>
        @foreach ($sources as $item)
            <a class="chip {{ $item === $source ? 'active' : '' }}" href="{{ route('deals', ['status' => $status->value, 'source' => $item->value]) }}">{{ $item->label() }}</a>
        @endforeach
    </nav>
@endsection

@section('content')
    @forelse ($items as $deal)
        <a class="card" href="{{ route('deals.show', $deal) }}" style="color:var(--ink)">
            <div class="between">
                <span style="font-size:15px;font-weight:600">{{ $deal->title() }}</span>
                <span class="badge" style="flex:none">{{ $deal->source->label() }}</span>
            </div>
            <p class="muted" style="color:var(--ink2);line-height:1.4">
                @if ($deal->contract)
                    {{ $deal->contract->subject }}, {{ $deal->contract->region->name }} · {{ $money->format($deal->contract->amount_kopecks) }}
                @else
                    {{ collect([$deal->service, $deal->facility])->filter()->implode(' · ') ?: 'Без описания' }}
                @endif
            </p>
            <div class="between muted" style="font-size:12px">
                <span>{{ $deal->received_at?->translatedFormat('j F') }}@if ($deal->offer) · КП: {{ $deal->offer->channel->label() }}@endif</span>
                <span style="font-weight:600;color:var(--ink2)">
                    @if ($deal->contract_amount_kopecks !== null)
                        Договор {{ $money->format($deal->contract_amount_kopecks) }}
                    @elseif ($deal->offer_amount_kopecks !== null)
                        КП {{ $money->format($deal->offer_amount_kopecks) }}
                    @elseif ($deal->remind_on)
                        Напомнить {{ $deal->remind_on->translatedFormat('j F') }}
                    @endif
                </span>
            </div>
        </a>
    @empty
        <div class="card empty">
            <h2>Здесь пока пусто</h2>
            <p class="muted">Сделки появляются, когда вы отправляете КП по тендеру или приходит заявка с сайта.</p>
        </div>
    @endforelse
@endsection
