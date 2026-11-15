@extends('layouts.app')

@section('title', 'Вход')

@section('content')
    <h1>Вход</h1>
    <form method="post" action="{{ route('login') }}" class="card">
        @csrf
        <label>
            Почта
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </label>
        @error('email')
            <p class="error">{{ $message }}</p>
        @enderror
        <label>
            Пароль
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        @error('password')
            <p class="error">{{ $message }}</p>
        @enderror
        <label class="check">
            <input type="checkbox" name="remember" value="1"> Запомнить меня
        </label>
        <button type="submit">Войти</button>
    </form>
@endsection
