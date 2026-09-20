@extends('layout')
@section('content')
    <main class="login-page">
        <div class="login-brand"><span class="brand-mark">m<span>t</span></span><strong>MTIS</strong></div>
        <form class="login-form" method="POST" action="{{ route('login.submit') }}">
            @csrf
            <div class="section-eyebrow">РАБОЧЕЕ ПРОСТРАНСТВО</div>
            <h1>Вход в MTIS</h1>
            <p class="muted">Управление задачами и загрузкой команды</p>
            @if ($errors->any())
                <div class="alert danger" role="alert">{{ $errors->first() }}</div>
            @endif
            <label>Email<input type="email" name="email" autocomplete="username" value="{{ old('email') }}"
                    placeholder="name@company.kz" required autofocus></label>
            <label>Пароль<input type="password" name="password" autocomplete="current-password" required></label>
            <label class="checkbox"><input type="checkbox" name="remember" value="1">Запомнить меня</label>
            <button class="button primary login-submit" type="submit">Войти</button>
        </form>
        <footer>MTIS <span>Внутренняя информационная система</span></footer>
    </main>
@endsection
