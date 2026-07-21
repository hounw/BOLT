<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function password(): View
    {
        return view('web.account.password');
    }

    public function updatePassword(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->symbols()],
        ]);

        $request->user()->update([
            'password' => $data['password'],
        ]);

        $request->session()->regenerate();

        $auditLogger->log('auth.password_updated', actor: $request->user());

        return redirect()->route('account.password')->with('status', 'Password updated.');
    }
}
