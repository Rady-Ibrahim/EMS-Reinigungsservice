<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/extra-orders/{id}/work-end
 *
 * Closes the employee's own execution of the order. The three site-security
 * confirmations are the point of the closing screen, so they are required as
 * booleans — accepting a missing field as "false" would let a client silently
 * skip the hand-over.
 */
class EndWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key_returned'     => ['required', 'boolean'],
            'windows_closed'   => ['required', 'boolean'],
            'alarm_activated'  => ['required', 'boolean'],
            'shift_note'       => ['nullable', 'string', 'max:200'],
            'work_end'         => ['nullable', 'date'],
            'gps_work_end_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_work_end_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'employee_notes'   => ['nullable', 'string', 'max:2000'],

            // The closing screen is the last chance to attach the "after"
            // photos, so they may travel with the hand-over. Only the paths
            // are stored; the files themselves are uploaded separately.
            'after_photos'     => ['nullable', 'array'],
            'after_photos.*'   => ['string'],
        ];
    }

    public function messages(): array
    {
        return [
            'key_returned.required'    => 'Bitte bestätigen, ob der Schlüssel/Karte zurückgegeben wurde.',
            'windows_closed.required'  => 'Bitte bestätigen, ob Fenster und Türen geschlossen sind.',
            'alarm_activated.required' => 'Bitte bestätigen, ob die Alarmanlage aktiviert wurde.',
            'shift_note.max'           => 'Die Schichtnotiz darf höchstens 200 Zeichen lang sein.',
        ];
    }

    /**
     * Only the security confirmations and their shift note — the time and GPS
     * are handled by the service, never taken raw from the payload.
     */
    public function securityPayload(): array
    {
        return $this->safe()->only([
            'key_returned', 'windows_closed', 'alarm_activated', 'shift_note',
            'after_photos',
        ]);
    }
}
