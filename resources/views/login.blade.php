@extends('layouts.app')

@section('title', 'Masuk — SkripsiAI')

@section('content')
<div class="login-wrap">
    <div class="card login-card">
        <div class="logo">🎓</div>
        <h1>SkripsiAI</h1>
        <p class="muted">AI Reviewer Skripsi &amp; Proposal — masuk untuk melanjutkan ke beranda.</p>

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
