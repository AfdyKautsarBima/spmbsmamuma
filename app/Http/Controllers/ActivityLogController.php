<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\DateInput;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ActivityLog::with('user')
            ->when($request->event, fn ($q, $e) => $q->where('event', $e))
            ->when($request->user_id, fn ($q, $u) => $q->where('user_id', $u))
            ->when(DateInput::iso($request->from), fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when(DateInput::iso($request->to), fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->when($request->q, fn ($q, $s) => $q->where('description', 'like', "%{$s}%"))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('activity-logs.index', [
            'logs'   => $logs,
            'events' => ActivityLog::query()->distinct()->orderBy('event')->pluck('event'),
            'users'  => User::orderBy('name')->get(['id', 'name', 'username']),
        ]);
    }
}
