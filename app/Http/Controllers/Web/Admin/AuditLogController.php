<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AuditEventEnum;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::with('user:id,name', 'auditable')
            ->when(
                $request->filled('event') && AuditEventEnum::tryFrom($request->input('event')),
                fn($q) => $q->where('event', $request->input('event'))
            )
            ->when(
                $request->filled('actor'),
                fn($q) => $q->where('user_id', (int) $request->input('actor'))
            )
            ->when(
                $request->filled('target_type'),
                fn($q) => $q->where('auditable_type', $request->input('target_type'))
            )
            ->when(
                $request->filled('from'),
                fn($q) => $q->whereDate('created_at', '>=', $request->input('from'))
            )
            ->when(
                $request->filled('to'),
                fn($q) => $q->whereDate('created_at', '<=', $request->input('to'))
            )
            ->latest()
            ->paginate(30);

        $events = AuditEventEnum::cases();

        return view('admin.audit_logs.index', compact('logs', 'events'));
    }

    public function show(AuditLog $auditLog): View
    {
        return view('admin.audit_logs.show', [
            'log' => $auditLog->load('user:id,name', 'auditable'),
        ]);
    }
}