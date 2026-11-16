@extends('layouts.app')

@section('title', 'Настройки')

@section('top')
    <h1>Настройки</h1>
    <p class="sub">Что искать и как отправлять</p>
@endsection

@section('content')
    <form method="post" action="{{ route('settings.update') }}" class="card">
        @csrf
        @method('put')
        <h2 class="section-title">Регионы</h2>
        @foreach ($regions as $region)
            <label class="check" style="min-height:44px;border-bottom:1px solid var(--chip);justify-content:space-between">
                <span>{{ $region->name }}</span>
                <input type="checkbox" name="regions[]" value="{{ $region->code }}" @checked($region->is_enabled)>
            </label>
        @endforeach
        @error('regions') <p class="error">{{ $message }}</p> @enderror
        @error('regions.*') <p class="error">{{ $message }}</p> @enderror

        <label>Сумма контракта от, ₽
            <input type="text" name="min_amount_rubles" value="{{ old('min_amount_rubles', $minAmountRubles) }}" inputmode="numeric" required>
        </label>
        @error('min_amount_rubles') <p class="error">{{ $message }}</p> @enderror
        <p class="muted">Законы: 44-ФЗ и 223-ФЗ. Разделы в документации: ЭОМ, сети связи, СКС, СКУД, видеонаблюдение, АПС и СОУЭ.</p>

        <button type="submit">Сохранить</button>
    </form>

    <section class="card">
        <h2 class="section-title">Отправка и подключения</h2>
        <div class="between"><span>Почта для КП</span><span class="badge {{ $mailReady ? 'on' : 'off' }}">{{ $mailReady ? 'подключена' : 'не подключена' }}</span></div>
        <div class="row">
            <a class="button secondary" href="{{ route('prices') }}">Прайс работ</a>
            <a class="button secondary" href="{{ route('template') }}">Шаблон и лимит писем</a>
            <a class="button secondary" href="{{ route('settings.telegram') }}">Telegram</a>
            <a class="button secondary" href="{{ route('settings.eis') }}">ЕИС</a>
        </div>
    </section>

    <section class="card">
        <h2 class="section-title">Нормативы ГЭСН и ставки труда (ФГИС ЦС)</h2>
        @if ($normatives === null)
            <p class="muted">Ещё не загружались. Сервер загружает их сам после выкладки и раз в неделю.</p>
        @elseif (($normatives['ok'] ?? false) === true)
            <p class="muted">Загружено {{ $normatives['finished_at'] ?? '' }}: норм — {{ $normatives['norms'] ?? 0 }}.</p>
            @foreach ($regions as $region)
                @if (isset($normatives['periods'][$region->code]))
                    <div class="between" style="font-size:14px"><span>{{ $region->name }}</span><span class="muted">ставки за {{ $normatives['periods'][$region->code] }}</span></div>
                @endif
            @endforeach
        @else
            <div class="flash err" role="alert">Последняя загрузка {{ $normatives['finished_at'] ?? '' }} не удалась: {{ $normatives['error'] ?? 'неизвестная ошибка' }}</div>
        @endif
        <form method="post" action="{{ route('settings.normatives') }}">
            @csrf
            <button type="submit" class="secondary">Обновить сейчас</button>
        </form>
    </section>

    <section class="card">
        <h2 class="section-title">Демо-тендеры</h2>
        <p class="muted">Вымышленные тендеры с пометкой «[Демо]», чтобы посмотреть экраны до подключения ЕИС.</p>
        @if ($demoLoaded)
            <form method="post" action="{{ route('settings.demo.remove') }}">
                @csrf
                @method('delete')
                <button type="submit" class="secondary">Удалить демо-тендеры</button>
            </form>
        @else
            <form method="post" action="{{ route('settings.demo') }}">
                @csrf
                <button type="submit" class="secondary">Загрузить демо-тендеры</button>
            </form>
        @endif
    </section>

    <section class="card">
        <div class="between">
            <span>{{ auth()->user()?->email }}</span>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="secondary">Выйти</button>
            </form>
        </div>
    </section>
@endsection
