<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Login por PIN (terminales locales) o email/password.
     */
    public function login(Request $request)
    {
        $request->validate(['pin_code' => ['nullable', 'string', 'regex:/^\d{4,6}$/']]);
        $identity = $request->filled('pin_code') ? 'pin' : mb_strtolower((string) $request->input('email'));
        $key = 'login:'.hash('sha256', $request->ip().'|'.$identity);
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->with('error', 'Demasiados intentos. Espera un minuto antes de volver a ingresar.');
        }
        RateLimiter::hit($key, 60);
        // Login por PIN
        if ($request->filled('pin_code')) {
            $user = User::findByPin($request->pin_code);
            if ($user && $user->hasPin()) {
                Auth::login($user);
                $request->session()->regenerate();
                RateLimiter::clear($key);
                return $this->redirectByRole($user);
            }
            return back()->with('error', 'PIN inválido o usuario inactivo.');
        }

        // Login por email/password
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            if (!$user->email || !$user->password) {
                Auth::logout();
                return back()->with('error', 'Este usuario solo puede ingresar con PIN.');
            }
            if (!$user->active) {
                Auth::logout();
                return back()->with('error', 'Tu cuenta está desactivada.');
            }
            $request->session()->regenerate();
            RateLimiter::clear($key);
            return $this->redirectByRole($user);
        }

        return back()->with('error', 'Credenciales incorrectas.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    private function redirectByRole(User $user)
    {
        return match ($user->role) {
            'cook'          => redirect()->route('cook.recipes'),
            'waiter'        => redirect()->route('waiter.orders'),
            'cashier'       => redirect()->route('cashier.pos'),
            'administrator' => redirect()->route('admin.dashboard'),
            'programmer'    => redirect()->route('programmer.panel'),
            default         => redirect('/'),
        };
    }
}
