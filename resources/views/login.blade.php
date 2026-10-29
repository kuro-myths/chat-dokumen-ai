@extends('layouts.app')

@section('title', 'Masuk — Kuro AI')

@section('content')
<div class="login-wrap">
    <div class="card login-card">
        <div class="kuro-wrap static" style="position:static;margin:0 auto 4px">
            <div class="kuro-bubble">Kuro menunggu kamu… 🐾</div>
            <svg class="kuro-svg" viewBox="0 0 120 112" width="92" aria-hidden="true">
                <g>
                    <path d="M30 48 L20 16 L48 36 Z" fill="#d9cff0" stroke="#c8b9e8" stroke-width="2"/>
                    <path d="M90 48 L100 16 L72 36 Z" fill="#d9cff0" stroke="#c8b9e8" stroke-width="2"/>
                    <ellipse cx="60" cy="64" rx="42" ry="40" fill="#ede8fe" stroke="#c8b9e8" stroke-width="2"/>
                    <ellipse class="kuro-eye" cx="46" cy="60" rx="7" ry="9" fill="#6d5efc"/>
                    <ellipse class="kuro-eye" cx="74" cy="60" rx="7" ry="9" fill="#6d5efc"/>
                    <path d="M52 79 Q60 87 68 79" stroke="#6d5efc" stroke-width="3" fill="none" stroke-linecap="round"/>
                    <path d="M22 66 L36 68 M22 76 L36 75" stroke="#c8b9e8" stroke-width="2" stroke-linecap="round"/>
                    <path d="M98 66 L84 68 M98 76 L84 75" stroke="#c8b9e8" stroke-width="2" stroke-linecap="round"/>
                </g>
            </svg>
        </div>
        <div class="logo" aria-hidden="true" style="display:flex;align-items:center;justify-content:center">
            <svg viewBox="0 0 64 64" width="30" height="30">
                <path d="M12 18c0-4 3-6 6-6h20c4 0 6 3 6 6v13c0 4-3 6-7 6H27l-7 7v-7c-4 0-8-3-8-7z" fill="#6d5efc"/>
                <text x="28" y="34" font-family="Arial" font-size="16" font-weight="bold" fill="#ffffff" text-anchor="middle">K</text>
            </svg>
        </div>
        <h1 style="margin-top:10px">Kuro AI</h1>
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
                <button type="submit" class="btn" style="width:100%;justify-content:center">Masuk →</button>
            </div>
        </form>

        <p class="hint">Password default: <code>123456789</code> (ubah via <code>APP_LOGIN_PASSWORD</code> di .env)</p>
    </div>
</div>
@endsection
