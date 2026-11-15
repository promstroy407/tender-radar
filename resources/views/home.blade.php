@extends('layouts.app')

@section('title', 'Главная')

@section('content')
    <h1>Здравствуйте, {{ auth()->user()?->name }}</h1>
    <div class="card">
        <p class="muted">Экраны тендеров по утверждённому макету появятся здесь после подключения сбора из ЕИС.</p>
        @can('update', \App\Models\Setting::class)
            <div class="row">
                <a class="button" href="{{ route('settings.telegram') }}">Настройки → Telegram</a>
                <a class="button" href="{{ route('settings.eis') }}">Настройки → ЕИС</a>
            </div>
        @endcan
    </div>

    <div class="card" id="install" hidden>
        <h2>Иконка на главном экране</h2>
        <p class="muted" id="install-hint">Откройте меню браузера и выберите «Добавить на главный экран» или «Установить приложение».</p>
        <div class="row">
            <button type="button" id="install-button" hidden>Установить приложение</button>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const card = document.getElementById('install');
        const hint = document.getElementById('install-hint');
        const button = document.getElementById('install-button');
        const installed = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
        const inTelegram = typeof window.TelegramWebviewProxy !== 'undefined' || /Telegram/i.test(navigator.userAgent);

        if (installed || inTelegram) {
            return;
        }

        if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
            hint.textContent = 'В Safari нажмите «Поделиться» (квадрат со стрелкой) → «На экран „Домой“» → «Добавить».';
        }
        card.hidden = false;

        let deferred = null;
        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            deferred = event;
            hint.textContent = 'Приложение откроется с иконки, без адресной строки.';
            button.hidden = false;
        });

        button.addEventListener('click', async () => {
            if (deferred === null) {
                return;
            }
            deferred.prompt();
            await deferred.userChoice;
            deferred = null;
            card.hidden = true;
        });

        window.addEventListener('appinstalled', () => { card.hidden = true; });
    })();
</script>
@endpush
