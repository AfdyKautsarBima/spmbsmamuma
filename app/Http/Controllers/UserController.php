<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\UserRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('username', 'like', "%{$s}%")))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', ['users' => $users, 'roles' => Role::cases()]);
    }

    public function create(): View
    {
        return view('users.form', [
            'user'  => new User(['role' => Role::Pendaftaran, 'is_active' => true]),
            'roles' => Role::cases(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated() + ['is_active' => true]);

        ActivityLog::record('user.created', "Akun {$user->username} dibuat", $user, null, [
            'name' => $user->name, 'username' => $user->username, 'role' => $user->role->value,
        ]);

        return redirect()->route('users.index')->with('success', "Akun {$user->username} berhasil dibuat.");
    }

    public function edit(User $user): View
    {
        return view('users.form', ['user' => $user, 'roles' => Role::cases()]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $isSelf = $user->is($request->user());

        if ($isSelf && $data['role'] !== Role::Admin->value) {
            return back()->withInput()->withErrors(['role' => 'Anda tidak dapat mengubah peran akun sendiri.']);
        }

        $password = $data['password'] ?? null;
        unset($data['password']);

        $user->fill($data);
        [$old, $new] = ActivityLog::changes($user);
        $user->save();

        if ($new) {
            ActivityLog::record('user.updated', "Akun {$user->username} diubah", $user, $old, $new);
        }

        if ($password) {
            $user->update(['password' => $password]);
            ActivityLog::record('user.password_reset', "Password akun {$user->username} direset admin", $user);
            if (! $isSelf) {
                $this->endSessions($user);
            }
        }

        return redirect()->route('users.index')->with('success', "Akun {$user->username} berhasil diperbarui.");
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        if ($user->is_active) {
            ActivityLog::record('user.activated', "Akun {$user->username} diaktifkan", $user);
        } else {
            ActivityLog::record('user.deactivated', "Akun {$user->username} dinonaktifkan", $user);
            $this->endSessions($user);
        }

        return back()->with('success', 'Status akun '.$user->username.' diperbarui.');
    }

    /** Paksa keluar semua sesi aktif milik akun tersebut. */
    private function endSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }
}
