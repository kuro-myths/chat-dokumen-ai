<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Halaman masuk sederhana berbasis password (default: 123456789,
 * bisa diubah via APP_LOGIN_PASSWORD di .env). Sesuai berbasis session.
 */
class AuthController extends Controller
{
    /** Tampilkan halaman masuk. Kalau sudah masuk, langsung ke beranda. */
    public function show()
    {
        if (session('logged_in')) {
            return redirect()->route('beranda');
        }

        return view('login');
    }

    public function attempt(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $expected = (string) config('ai.login.password', '123456789');

        if (hash_equals($expected, (string) $request->input('password'))) {
            $request->session()->regenerate();
            $request->session()->put('logged_in', true);

            return redirect()->intended(route('beranda'));
        }

        // Anti brute-force ringan: jeda 1 detik saat password salah.
        usleep(500_000);

        return back()->withErrors(['password' => 'Password salah.'])->withInput();
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('logged_in');
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
