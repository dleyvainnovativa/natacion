<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function attempt(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Solo cuentas activas pueden entrar.
        if (Auth::attempt([...$credentials, 'active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('schedule.index'));
        }

        return back()->withErrors([
            'email' => 'Credenciales incorrectas o cuenta inactiva.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
