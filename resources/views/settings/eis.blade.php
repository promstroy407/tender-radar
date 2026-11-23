@extends('layouts.app')

@section('title', 'Настройки ЕИС')

@section('content')
    <h1>Настройки → ЕИС</h1>

    <form method="post" action="{{ route('settings.eis.update') }}" class="card" autocomplete="off">
        @csrf
        @method('put')
        <div>
            <h2>Токен ЕИС
                <span class="badge {{ $tokenSource ? 'on' : 'off' }}">
                    {{ match ($tokenSource) { 'settings' => 'задан здесь', 'env' => 'задан в .env на сервере', default => 'не задан' } }}
                </span>
            </h2>
            <p class="muted">Токен получателя данных с zakupki.gov.ru/pmd (вход через Госуслуги). Сохранённый токен не показывается.</p>
        </div>
        <label>
            Новый токен
            <input type="password" name="token" autocomplete="off" spellcheck="false">
        </label>
        @error('token')
            <p class="error">{{ $message }}</p>
        @enderror
        @if ($tokenSource === 'settings')
            <label class="check"><input type="checkbox" name="clear_token" value="1"> Удалить сохранённый токен</label>
        @endif
        <button type="submit">Сохранить</button>
    </form>

    <div class="card">
        <h2>Проверка доступа</h2>
        <p class="muted">Сервер запросит у ЕИС схему сервиса и реестр контрактов Марий Эл за последний рабочий день.</p>
        <form method="post" action="{{ route('settings.eis.probe') }}">
            @csrf
            <button type="submit" class="secondary">Проверить доступ к ЕИС</button>
        </form>

        @if ($report)
            <p class="muted">Последняя проверка: {{ $report['finished_at'] ?? '—' }}</p>
            @if ($report['ok'] ?? false)
                <div class="flash ok">Доступ есть. Тип документа: {{ $report['document_type'] ?? '—' }}. Архивов: {{ $report['archives'] ?? 0 }}.</div>
            @else
                <div class="flash err">{{ $report['error'] ?? 'Ошибка' }}</div>
            @endif

            @foreach (($report['schema'] ?? []) as $type => $values)
                @if (str_contains(strtolower($type), 'subsystem') || str_contains(strtolower($type), 'documenttype'))
                    <details>
                        <summary>{{ $type }} ({{ count($values) }})</summary>
                        <p class="muted" style="word-break: break-word">{{ implode(', ', $values) }}</p>
                    </details>
                @endif
            @endforeach

            @if (! empty($report['sample']['files']))
                <details>
                    <summary>Файлы в архиве ({{ count($report['sample']['files']) }})</summary>
                    <p class="muted" style="word-break: break-word">{{ implode(', ', $report['sample']['files']) }}</p>
                </details>
            @endif
            @if (! empty($report['sample']['xml']))
                <details>
                    <summary>Образец XML: {{ $report['sample']['first_file'] }}</summary>
                    <pre style="white-space: pre-wrap; word-break: break-all; font-size: 12px; max-height: 480px; overflow: auto">{{ $report['sample']['xml'] }}</pre>
                </details>
            @endif
        @endif
    </div>
@endsection
