<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarException extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['date' => 'date:Y-m-d', 'hours' => 'float'];
}
