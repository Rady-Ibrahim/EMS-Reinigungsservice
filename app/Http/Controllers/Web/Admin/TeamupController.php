<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeamupSetting;
use App\Services\TeamupClient;
use App\Services\TeamupSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamupController extends Controller
{
    public function __construct(
        private readonly TeamupSyncService $sync,
        private readonly TeamupClient $client
    ) {
    }

    public function edit(): View
    {
        $settings = TeamupSetting::current();

        return view('admin.teamup.edit', [
            'settings'   => $settings,
            'isConfigured' => $settings->isConfigured(),
            'lastSync'   => \App\Models\TeamupSyncState::latest('last_pushed_at')
                ->whereNotNull('last_pushed_at')->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'calendar_key'        => ['nullable', 'string', 'max:64'],
            'api_key'             => ['nullable', 'string', 'max:128'],
            'timezone'            => ['nullable', 'string', 'max:64'],
            'enabled'             => ['nullable', 'boolean'],
            'subcalendar_default' => ['nullable', 'integer'],
            'subcalendar_fix_schedule' => ['nullable', 'integer'],
            'subcalendar_extra_auftrag' => ['nullable', 'integer'],
            'subcalendar_employee_shift' => ['nullable', 'integer'],
            'subcalendar_personal_appointment' => ['nullable', 'integer'],
            'subcalendar_internal_event' => ['nullable', 'integer'],
        ]);

        $settings = TeamupSetting::first() ?? new TeamupSetting();

        $subcalendars = array_filter([
            'default'             => $request->input('subcalendar_default'),
            'fix_schedule'        => $request->input('subcalendar_fix_schedule'),
            'extra_auftrag'       => $request->input('subcalendar_extra_auftrag'),
            'employee_shift'      => $request->input('subcalendar_employee_shift'),
            'personal_appointment'=> $request->input('subcalendar_personal_appointment'),
            'internal_event'      => $request->input('subcalendar_internal_event'),
        ], fn($v) => $v !== null && $v !== '');

        $settings->fill([
            'calendar_key'    => $request->input('calendar_key', $settings->calendar_key),
            'api_key'         => $request->input('api_key', $settings->api_key),
            'timezone'        => $request->input('timezone') ?: 'Europe/Berlin',
            'enabled'         => (bool) $request->input('enabled', false),
            'subcalendar_ids' => $subcalendars ?: null,
        ]);

        // Never clear a previously saved secret when the field is left blank
        if ($request->input('calendar_key') === null && $settings->exists === false) {
            $settings->calendar_key = null;
        }

        $settings->save();

        // Validate credentials against Teamup when enabled
        if ($settings->enabled && $settings->calendar_key && $settings->api_key) {
            try {
                $this->client->checkAccess();
                $this->sync->prime(now()->startOfMonth(), now()->addMonths(2)->endOfMonth());
            } catch (\Throwable $e) {
                return back()
                    ->withErrors(['api_key' => 'Teamup-Verbindung fehlgeschlagen: '.$e->getMessage()])
                    ->withInput();
            }
        }

        return redirect()->route('admin.teamup.edit')
                         ->with('success', __('messages.success'));
    }

    /**
     * Manual "Sync now" — flushes pending pushes + pulls Teamup changes.
     */
    public function sync(): RedirectResponse
    {
        $summary = $this->sync->syncAll();

        if (($summary['enabled'] ?? false) === false) {
            return back()->with('error', 'Teamup ist nicht konfiguriert.');
        }

        $message = sprintf(
            '✓ Push: %d, Pull: %d, Übersprungen: %d, Fehler: %d',
            $summary['pushed'],
            $summary['pulled'],
            $summary['skipped'],
            $summary['failed'],
        );

        if (! empty($summary['errors'])) {
            return back()->with('error', $message."\n".implode("\n", $summary['errors']));
        }

        return back()->with('success', $message);
    }
}