@extends('layouts.app')

@section('title', 'Вход')

@section('top')
    <h1>Тендер-Радар</h1>
    <p class="sub">Вход по почте и паролю</p>
@endsection

@section('main_class', 'narrow-bottom')

@section('content')
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
