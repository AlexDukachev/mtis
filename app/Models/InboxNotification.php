<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxNotification extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['read_at' => 'datetime'];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
