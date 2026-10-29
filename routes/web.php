<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ConvController;
use App\Http\Controllers\Web\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman web Kuro AI
|--------------------------------------------------------------------------
| Menggantikan halaman welcome default Laravel ("/" tidak lagi menampilkan
| "Let's get started"). "/" kini menjadi halaman BERANDA yang dilindungi
| halaman masuk (password lihat config/ai.php -> login).
*/

Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'attempt']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::get('/', [HomeController::class, 'index'])->name('beranda');

// AJAX (butuh login): daftar provider + model AI
Route::get('/models', [HomeController::class, 'models']);
Route::get('/models/{slug}', [HomeController::class, 'showModels']);

// AJAX (butuh login): uji coba chat AI dari beranda
Route::post('/chat', [HomeController::class, 'chat']);

// AJAX (butuh login): riwayat percakapan — tersimpan di DATABASE
Route::get('/convs', [ConvController::class, 'index']);
Route::post('/convs', [ConvController::class, 'store']);
Route::delete('/convs', [ConvController::class, 'clearAll']);
Route::get('/convs/{id}', [ConvController::class, 'show']);
Route::delete('/convs/{id}', [ConvController::class, 'destroy']);
