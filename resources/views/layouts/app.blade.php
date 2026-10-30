<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Kuro AI')</title>

{{-- Favicon eksternal (public/img/kuro-logo.svg) --}}
<link rel="icon" type="image/svg+xml" href="{{ asset('img/kuro-logo.svg') }}">

{{-- Style Kuro AI (public/css/kuro.css) — lokal & dimuat pertama agar halaman langsung tampil --}}
<link rel="stylesheet" href="{{ asset('css/kuro.css') }}?v=20261029-5">

{{-- Komponen via CDN (Google Fonts + Font Awesome) dimuat ASINKRON lewat
     public/js/asset-loader.js — bukan <link> render-blocking — supaya CDN
     yang lambat/terblokir tidak membuat halaman tampak putih kosong.
     Class custom Kuro memakai prefiks sendiri sehingga urutan muat aman. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
<script src="{{ asset('js/asset-loader.js') }}?v=1" defer></script>
<noscript>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</noscript>

@stack('styles')
</head>
<body>
@yield('content')
@stack('scripts')
</body>
</html>
