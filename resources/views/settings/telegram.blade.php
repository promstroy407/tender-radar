@extends('layouts.app')

@section('title', 'Настройки Telegram')

@php
    $sourceLabel = fn (?string $source): string => match ($source) {
        'settings' => 'задан здесь',
        'env' => 'задан в .env на сервере',
        default => 'не задан',
    };
@endphp

@section('content')
    <h1>Настройки → Telegram</h1>

    <form method="post" action="{{ route('settings.telegram.update') }}" class="card" autocomplete="off">
        @csrf
        @method('put')

        <div>
            <h2>Токен бота
                <span class="badge {{ $tokenSource ? 'on' : 'off' }}">{{ $sourceLabel($tokenSource) }}</span>
            </h2>
            <p class="muted">Из сообщения @BotFather. Сохранённый токен здесь не показывается; чтобы заменить — вставьте новый.</p>
        </div>
        <label>
            Новый токен
            <input type="password" name="bot_token" placeholder="123456789:AAH…" autocomplete="off" spellcheck="false">
        </label>
        @error('bot_token')
            <p class="error">{{ $message }}</p>
        @enderror
        @if ($tokenSource === 'settings')
            <label class="check"><input type="checkbox" name="clear_bot_token" value="1"> Удалить сохранённый токен</label>
        @endif

        <div>
            <h2>Адрес пересыльщика
                <span class="badge {{ $apiUrlSource ? 'on' : 'off' }}">{{ $apiUrlSource ? $sourceLabel($apiUrlSource) : 'не задан — https://api.telegram.org' }}</span>
            </h2>
            <p class="muted">Строка вида https://tg.udallenka.ru/… из файла tg-tender.txt. С московского сервера без него Telegram недоступен.</p>
        </div>
        <label>
            Новый адрес
            <input type="password" name="api_url" placeholder="https://tg.udallenka.ru/…" autocomplete="off" spellcheck="false">
        </label>
        @error('api_url')
            <p class="error">{{ $message }}</p>
        @enderror
        @if ($apiUrlSource === 'settings')
            <label class="check"><input type="checkbox" name="clear_api_url" value="1"> Удалить сохранённый адрес</label>
        @endif

        <div>
            <h2>Как получать сообщения</h2>
            <p class="muted">Если Telegram не может достучаться до сервера (ошибка «Connection timed out»), выберите опрос.</p>
        </div>
        <label class="check">
            <input type="radio" name="updates_mode" value="polling" @checked($updatesMode === 'polling')>
            Опрос: сервер сам забирает сообщения через пересыльщик раз в минуту
        </label>
        <label class="check">
            <input type="radio" name="updates_mode" value="webhook" @checked($updatesMode === 'webhook')>
            Webhook: Telegram сам присылает сообщения на сервер
        </label>
        @error('updates_mode')
            <p class="error">{{ $message }}</p>
        @enderror

        <button type="submit">Сохранить</button>
    </form>

    <div class="card">
        <h2>Уведомления о заявках
            <span class="badge {{ $ownerLinked ? 'on' : 'off' }}">{{ $ownerLinked ? 'включены' : 'не включены' }}</span>
        </h2>
        <p class="muted">Новая заявка с сайта es12.ru придёт сообщением от бота. Нажмите кнопку, откройте ссылку на телефоне и нажмите «Запустить» — уведомления пойдут в этот чат. Ссылка одноразовая.</p>
        @if (session('owner_link'))
            <a class="button" href="{{ session('owner_link') }}" target="_blank" rel="noopener">Открыть бота в Telegram</a>
        @endif
        <div class="row">
            <form method="post" action="{{ route('settings.telegram.owner') }}">
                @csrf
                <button type="submit" class="secondary">{{ $ownerLinked ? 'Привязать другой чат' : 'Получить ссылку' }}</button>
            </form>
            @if ($ownerLinked)
                <form method="post" action="{{ route('settings.telegram.owner.destroy') }}">
                    @csrf
                    @method('delete')
                    <button type="submit" class="secondary">Отключить</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card">
        <h2>Проверка</h2>
        <p class="muted">Секрет webhook: {{ $webhookSecretSet ? 'создан на сервере' : 'не создан — выполните telegram:webhook-secret' }}.</p>
        <div class="row">
            <form method="post" action="{{ route('settings.telegram.check') }}">
                @csrf
                <button type="submit" class="secondary">Проверить бота</button>
            </form>
            <form method="post" action="{{ route('settings.telegram.webhook') }}">
                @csrf
                <button type="submit" class="secondary">Включить приём сообщений</button>
            </form>
        </div>
    </div>
@endsection
