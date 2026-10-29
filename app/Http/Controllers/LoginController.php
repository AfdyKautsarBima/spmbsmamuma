<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 300;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [], ['username' => 'username', 'password' => 'password']);

        $username = Str::lower(trim($data['username']));
        $key = 'login:'.$username.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'username' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        // Akun nonaktif sengaja mendapat pesan yang sama dengan password salah.
        $ok = Auth::attempt(
            ['username' => $username, 'password' => $data['password'], 'is_active' => true],
            $request->boolean('remember'),
        );

        if (! $ok) {
            RateLimiter::hit($key, self::LOCK_SECONDS);
            ActivityLog::record('login_failed', "Login gagal untuk username '{$username}'");

            throw ValidationException::withMessages(['username' => 'Username atau password salah.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        ActivityLog::record('login', "{$user->username} login");

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        ActivityLog::record('logout', "{$request->user()->username} logout");

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
