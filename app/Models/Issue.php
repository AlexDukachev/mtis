<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    public const FINAL = ['closed', 'cancelled'];

    public const DEVELOPMENT = ['ready', 'development', 'returned', 'blocked'];

    public const PLANNABLE = ['new', 'review', 'clarification', 'ready', 'development', 'returned', 'blocked', 'deferred'];

    protected $guarded = ['id'];

    protected $casts = ['tags' => 'array', 'estimate' => 'float', 'remaining' => 'float', 'due_date' => 'date:Y-m-d', 'planned_start' => 'date:Y-m-d', 'planned_end' => 'date:Y-m-d', 'qa_queued_at' => 'datetime', 'qa_started_at' => 'datetime', 'closed_at' => 'datetime'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tester_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function worklogs(): HasMany
    {
        return $this->hasMany(Worklog::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(Issue::class, 'parent_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role !== 'admin') {
            $query->whereHas('project', fn ($q) => $q->whereIn('id', $user->visibleProjectIds()));
        }
    }
}
