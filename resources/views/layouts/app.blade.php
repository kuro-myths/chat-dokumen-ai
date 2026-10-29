<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Kuro AI')</title>

{{-- Favicon eksternal (public/img/kuro-logo.svg) --}}
<link rel="icon" type="image/svg+xml" href="{{ asset('img/kuro-logo.svg') }}">

{{-- Komponen via CDN: Google Fonts + Font Awesome --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

{{-- Style Kuro AI (public/css/kuro.css) — dimuat terakhir agar tidak tertimpa --}}
<link rel="stylesheet" href="{{ asset('css/kuro.css') }}?v=20261029-5">

@stack('styles')
</head>
<body>
@yield('content')
@stack('scripts')
</body>
</html>

