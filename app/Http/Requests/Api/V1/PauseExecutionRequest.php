<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/extra-orders/{id}/pause
 *
 * Toggles the unpaid break: sending `action=start` opens a pause, sending
 * `action=stop` closes it and freezes the minutes. A single toggle endpoint
 * keeps the mobile client simple while the explicit action keeps the server
 * from guessing.
 */
class PauseExecutionRequest extends FormRequest
{
    public const ACTION_START = 'start';

    public const ACTION_STOP = 'stop';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action'    => ['required', 'in:'.self::ACTION_START.','.self::ACTION_STOP],

            // Optional in both directions: the service falls back to the
            // current time, which is what the app sends when the user only
            // taps "resume" without a recorded timestamp.
            'paused_at' => ['nullable', 'date'],
        ];
    }

    public function isStarting(): bool
    {
        return $this->input('action') === self::ACTION_START;
    }
}
