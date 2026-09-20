<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\CalendarException;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->admin($request);

        return response()->json(['users' => User::with(['teams', 'projects'])->get(), 'teams' => Team::with('members')->get(),
            'events' => AuditEvent::with('user')->latest('id')->limit(100)->get(),
            'thresholds' => Setting::read('thresholds', config('tracker.thresholds')), 'permissions' => Setting::read('permissions', config('tracker.permissions')),
            'calendar' => CalendarException::orderByDesc('date')->limit(100)->get()]);
    }

    public function user(Request $request, ?User $user = null): JsonResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'name' => 'required|string|max:120', 'username' => ['required', 'alpha_dash:ascii', 'max:60', Rule::unique('users')->ignore($user?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', Password::min(12)->mixedCase()->numbers()->symbols()],
            'role' => ['required', Rule::in(array_keys(config('tracker.roles')))], 'active' => 'required|boolean',
            'department' => 'nullable|string|max:120', 'position' => 'nullable|string|max:120', 'weekly_capacity' => 'required|numeric|min:0|max:168',
            'wip_limit' => 'required|integer|min:1|max:50', 'work_days' => 'required|array|max:7', 'work_days.*' => 'integer|between:1,7|distinct',
            'team_ids' => 'present|array', 'team_ids.*' => 'integer|exists:teams,id', 'project_ids' => 'present|array', 'project_ids.*' => 'integer|exists:projects,id',
        ]);
        if ($user?->id === $request->user()->id) {
            abort_unless($data['active'] && $data['role'] === 'admin', 422, 'Нельзя отключить себя или снять свою роль администратора.');
        }

        return DB::transaction(function () use ($request, $user, $data) {
            $teams = $data['team_ids'];
            $projects = $data['project_ids'];
            unset($data['team_ids'], $data['project_ids']);
            if (empty($data['password'])) {
                unset($data['password']);
            }
            $before = $user?->only(['name', 'role', 'active', 'weekly_capacity']);
            $user ??= new User;
            $user->fill($data)->save();
            $user->teams()->sync($teams);
            $user->projects()->sync($projects);
            AuditEvent::create(['user_id' => $request->user()->id, 'action' => 'user_saved', 'changes' => ['user_id' => $user->id, 'before' => $before, 'after' => $user->only(['name', 'role', 'active', 'weekly_capacity'])]]);

            return response()->json($user, $user->wasRecentlyCreated ? 201 : 200);
        });
    }

    public function team(Request $request): JsonResponse
    {
        $this->admin($request);
        $data = $request->validate(['name' => 'required|string|max:120', 'leader_id' => ['required', Rule::exists('users', 'id')->whereIn('role', ['manager', 'admin'])->where('active', true)]]);

        return DB::transaction(function () use ($request, $data) {
            $team = Team::create($data);
            $team->members()->attach($data['leader_id']);
            AuditEvent::create(['user_id' => $request->user()->id, 'action' => 'team_created', 'changes' => ['team_id' => $team->id]]);

            return response()->json($team, 201);
        });
    }

    public function project(Request $request, ?Project $project = null): JsonResponse
    {
        abort_unless($request->user()->role === 'admin' || $request->user()->role === 'manager', 403);
        if ($project) {
            abort_unless($request->user()->manages($project), 403);
        }
        $data = $request->validate(['name' => 'required|string|max:120', 'key' => ['required', 'regex:/^[A-Z][A-Z0-9]{1,12}$/', Rule::unique('projects')->ignore($project?->id)],
            'description' => 'nullable|string|max:5000', 'team_id' => 'required|exists:teams,id', 'owner_id' => 'required|exists:users,id',
            'status' => ['required', Rule::in(['active', 'paused', 'archived'])], 'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'member_ids' => 'required|array|min:1', 'member_ids.*' => 'integer|exists:users,id']);
        $team = Team::findOrFail($data['team_id']);
        abort_unless($request->user()->role === 'admin' || $team->leader_id === $request->user()->id, 403);
        abort_unless(in_array((int) $data['owner_id'], array_map('intval', $data['member_ids']), true), 422, 'Ответственный должен быть участником проекта.');

        return DB::transaction(function () use ($request, $project, $data) {
            $members = $data['member_ids'];
            unset($data['member_ids']);
            $project ??= new Project;
            $project->fill($data)->save();
            $project->members()->sync($members);
            AuditEvent::create(['user_id' => $request->user()->id, 'action' => 'project_saved', 'changes' => ['project_id' => $project->id, 'members' => $members]]);

            return response()->json($project, $project->wasRecentlyCreated ? 201 : 200);
        });
    }

    public function settings(Request $request): JsonResponse
    {
        $this->admin($request);
        $data = $request->validate(['thresholds' => 'sometimes|array:reserve,overload', 'thresholds.reserve' => 'required_with:thresholds|numeric|min:0|max:100',
            'thresholds.overload' => 'required_with:thresholds|numeric|gte:thresholds.reserve|max:500',
            'permissions' => 'sometimes|array:manager,reporter,developer,tester,observer', 'permissions.*' => 'array',
            'permissions.*.*' => [Rule::in(['create', 'comment', 'assign', 'estimate', 'develop', 'test', 'accept', 'reopen', 'reports', 'worklog'])],
            'types' => 'sometimes|array|min:1', 'types.*' => 'required|string|max:60']);
        if (isset($data['types'])) {
            foreach (array_keys($data['types']) as $key) {
                abort_unless(is_string($key) && preg_match('/^[a-z][a-z0-9_]{0,30}$/', $key), 422, 'Ключ типа должен содержать латинские буквы.');
            }
        }
        DB::transaction(function () use ($data, $request) {
            foreach ($data as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
            AuditEvent::create(['user_id' => $request->user()->id, 'action' => 'settings_updated', 'changes' => $data]);
        });

        return response()->json(['ok' => true]);
    }

    public function workflow(Request $request, Project $project): JsonResponse
    {
        $this->admin($request);
        $data = $request->validate(['workflow' => 'required|array', 'workflow.*' => 'array', 'workflow.*.*' => [Rule::in(array_keys(config('tracker.statuses')))]]);
        foreach (array_keys($data['workflow']) as $key) {
            abort_unless(array_key_exists($key, config('tracker.statuses')), 422);
        }
        DB::transaction(function () use ($project, $data, $request) {
            $project->update($data);
            AuditEvent::create(['user_id' => $request->user()->id, 'action' => 'workflow_updated', 'changes' => ['project_id' => $project->id, ...$data]]);
        });

        return response()->json($project);
    }

    public function calendar(Request $request): JsonResponse
    {
        $this->admin($request);
        $data = $request->validate(['user_id' => 'nullable|exists:users,id', 'date' => 'required|date', 'hours' => 'required|numeric|min:0|max:24', 'reason' => 'required|string|max:255']);
        $entry = DB::transaction(function () use ($data, $request) {
            $entry = CalendarException::updateOrCreate(['user_id' => $data['user_id'] ?? null, 'date' => $data['date']], $data);
            AuditEvent::create(['user_id' => $request->user()->id, 'action' => 'calendar_updated', 'changes' => $data]);

            return $entry;
        });

        return response()->json($entry);
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }
}
