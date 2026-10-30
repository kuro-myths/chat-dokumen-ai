/* ============================================================
   Kuro AI — pemuat CSS CDN async (public/js/asset-loader.js)
   Google Fonts + Font Awesome dimuat dari sini, BUKAN sebagai
   <link> render-blocking di layout. Jaringan/ISP yang lambat ke
   CDN tidak lagi membuat halaman tampak putih kosong: halaman
   dirender lebih dulu dengan kuro.css (same-origin), lalu font
   dan ikon menyusul di belakang layar.
   ============================================================ */
(function () {
    'use strict';

    function loadCss(href) {
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    }

    loadCss('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;800&display=swap');
    loadCss('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');
})();
