<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'department', 'position', 'active', 'weekly_capacity', 'work_days', 'wip_limit', 'notification_preferences', 'theme'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'weekly_capacity' => 'float',
            'work_days' => 'array',
            'notification_preferences' => 'array',
        ];
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    public function visibleProjectIds(): Collection
    {
        return Project::query()->when($this->role !== 'admin', function ($q) {
            $q->whereHas('members', fn ($members) => $members->where('users.id', $this->id));
            if ($this->role === 'manager') {
                $q->orWhereHas('team', fn ($team) => $team->where('leader_id', $this->id));
            }
        })->pluck('id');
    }

    public function manages(Project $project): bool
    {
        return $this->role === 'admin' || ($this->role === 'manager' && $project->team->leader_id === $this->id);
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->active) {
            return false;
        }
        if ($this->role === 'admin') {
            return true;
        }
        $permissions = Setting::read('permissions', config('tracker.permissions'));

        return in_array($permission, $permissions[$this->role] ?? [], true);
    }
}
