@extends('layouts.app')

@section('title', 'Masuk — Kuro AI')

@section('content')
<div class="login-wrap">
    <div class="card login-card">
        <div class="kuro-wrap static">
            <div class="kuro-bubble">Kuro menunggu kamu… 🐾</div>
            <img class="kuro-svg" src="{{ asset('img/kuro.svg') }}" alt="Maskot Kuro" width="92">
        </div>
        <img class="login-logo" src="{{ asset('img/kuro-logo.svg') }}" alt="Logo Kuro AI">
        <h1 style="margin-top:4px">Kuro AI</h1>
        <p class="muted">Voice · Cek Model · Periksa Dokumen — masuk untuk melanjutkan.</p>

        @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <label for="password">Password</label>
            <input id="password" type="password" name="password" placeholder="••••••••••"
                   value="{{ old('password') }}" autofocus autocomplete="current-password" required>
            <div style="margin-top:18px">
                <button type="submit" class="btn" style="width:100%;justify-content:center">
                    <i class="fa-solid fa-right-to-bracket"></i> Masuk
                </button>
            </div>
        </form>

        <p class="hint">Password default: <code>123456789</code> (ubah via <code>APP_LOGIN_PASSWORD</code> di .env)</p>
    </div>
</div>
@endsection
