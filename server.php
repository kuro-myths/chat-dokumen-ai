<?php

/**
 * Router PHP built-in server (dipakai Railway lewat Procfile).
 * Setara "php artisan serve", tapi dengan batas unggah PHP yang
 * bisa diandalkan (flag -d di Procfile tidak diteruskan artisan
 * serve ke proses php -S anak, jadi server dijalankan langsung):
 * - file statis di /public disajikan apa adanya,
 * - selain itu diteruskan ke public/index.php (Laravel).
 */
$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if ($uri !== '/' && is_file(__DIR__.'/public'.$uri)) {
    return false; // biarkan built-in server menyajikan file statis (css/js/img)
}

require_once __DIR__.'/public/index.php';
