<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('password.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [], [
            'current_password' => 'password saat ini',
            'password'         => 'password baru',
        ]);

        $request->user()->update(['password' => $data['password']]);
        ActivityLog::record('password.changed', "{$request->user()->username} mengganti password sendiri");

        return back()->with('success', 'Password berhasil diganti.');
    }
}
