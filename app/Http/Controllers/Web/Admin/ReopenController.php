<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExtraAuftrag;
use App\Models\FixObjectSchedule;
use App\Services\OrderReopenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReopenController extends Controller
{
    public function __construct(private readonly OrderReopenService $service)
    {
    }

    public function reopenExtra(Request $request, ExtraAuftrag $extraAuftrag): RedirectResponse
    {
        $validation = $request->validate([
            'reopen_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->service->reopenExtra($extraAuftrag, Auth::user(), $validation['reopen_reason']);

            return redirect()->route('admin.extra-auftraege.show', $extraAuftrag->id)
                ->with('success', 'Der Auftrag wurde wieder geöffnet.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    public function reopenSchedule(Request $request, FixObjectSchedule $schedule): RedirectResponse
    {
        $validation = $request->validate([
            'reopen_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->service->reopenFix($schedule, Auth::user(), $validation['reopen_reason']);

            return redirect()->route('admin.calendar.schedules.reassign', $schedule->id)
                ->with('success', 'Der Termin wurde wieder geöffnet.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }
}