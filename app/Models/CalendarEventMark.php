<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarEventMark extends Model
{
    protected $fillable = [
        'event_type',
        'event_id',
        'marked_by',
    ];
}
