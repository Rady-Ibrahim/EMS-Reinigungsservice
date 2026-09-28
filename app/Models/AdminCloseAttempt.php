<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A refused attempt to close a field execution.
 *
 * This is a trace, not an inbox item: `AdminAlertEvaluator` turns a
 * still-outstanding attempt into a single admin alert, while the mobile app
 * keeps showing the 422 to the employee.
 *
 * @property int                          $id
 * @property int                          $extra_auftrag_id
 * @property int                          $user_id
 * @property array<int, string>           $reasons
 * @property string                       $dedupe_key
 */
class AdminCloseAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'extra_auftrag_id',
        'user_id',
        'reasons',
        'dedupe_key',
    ];

    protected function casts(): array
    {
        return [
            'reasons' => 'array',
        ];
    }

    public function extraAuftrag(): BelongsTo
    {
        return $this->belongsTo(ExtraAuftrag::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
