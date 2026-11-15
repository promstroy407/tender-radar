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
@endsection
