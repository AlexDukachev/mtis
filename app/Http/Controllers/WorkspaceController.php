<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\InboxNotification;
use App\Models\Issue;
use App\Models\Project;
use App\Models\SavedFilter;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use App\Services\BusinessCalendar;
use App\Services\Workload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function updateTeam(Request $request, Team $team): JsonResponse
    {
        abort_unless($request->user()->role === 'admin' || ($request->user()->role === 'manager' && $team->leader_id === $request->user()->id), 403);
        $data = $request->validate(['name' => 'required|string|max:255', 'member_ids' => 'present|array|max:500', 'member_ids.*' => 'integer|distinct|exists:users,id']);
        $ids = collect($data['member_ids'])->push($team->leader_id)->unique()->values();
        if ($request->user()->role !== 'admin') {
            $allowed = User::whereHas('projects', fn ($q) => $q->whereIn('projects.id', $request->user()->visibleProjectIds()))
                ->orWhereHas('teams', fn ($q) => $q->where('teams.id', $team->id))->pluck('id')->push($team->leader_id);
            abort_unless($ids->diff($allowed)->isEmpty(), 403);
        }
        DB::transaction(function () use ($team, $data, $ids, $request) {
            $before = $team->members()->pluck('users.id')->all();
            $team->update(['name' => $data['name']]);
            $team->members()->sync($ids);
            AuditEvent::create(['user_id' => $request->user()->id, 'action' => 'team_updated', 'changes' => ['team_id' => $team->id, 'members' => ['from' => $before, 'to' => $ids->all()]]]);
        });

        return response()->json($team);
    }

    public function users(Request $request): JsonResponse
    {
        $ids = $request->user()->visibleProjectIds();

        return response()->json(['data' => User::whereHas('projects', fn ($q) => $q->whereIn('projects.id', $ids))->with('teams')->get()]);
    }

    public function projects(Request $request): JsonResponse
    {
        return response()->json(['data' => Project::whereIn('id', $request->user()->visibleProjectIds())->with('members')->get()]);
    }

    public function teams(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => Team::when($user->role !== 'admin', fn ($q) => $q->whereHas('members', fn ($m) => $m->where('users.id', $user->id))->orWhere('leader_id', $user->id))->get()]);
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $ids = $user->visibleProjectIds();
        $projects = Project::whereIn('id', $ids)->with('members')->withCount('issues')->get();
        $users = User::when($user->role !== 'admin', fn ($q) => $q->where(function ($scope) use ($user, $ids) {
            $scope->whereHas('projects', fn ($p) => $p->whereIn('projects.id', $ids));
            if ($user->role === 'manager') {
                $scope->orWhereHas('teams', fn ($t) => $t->where('leader_id', $user->id));
            }
        }))->with('teams')->get();
        $teams = Team::when($user->role !== 'admin', fn ($q) => $q->whereHas('members', fn ($m) => $m->where('users.id', $user->id))->orWhere('leader_id', $user->id))->get();

        return response()->json(['user' => $user, 'projects' => $projects, 'users' => $users, 'teams' => $teams,
            'statuses' => config('tracker.statuses'), 'types' => Setting::read('types', config('tracker.types')), 'priorities' => config('tracker.priorities'), 'roles' => config('tracker.roles'),
            'permissions' => collect(['create', 'assign', 'estimate', 'comment', 'worklog', 'reports'])->filter(fn ($p) => $user->hasPermission($p))->values(),
            'filters' => SavedFilter::where('user_id', $user->id)->get(), 'unread' => $this->notificationsQuery($request)->whereNull('read_at')->count()]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $query = Issue::visibleTo($request->user());
        if (! $request->user()->hasPermission('reports')) {
            $id = $request->user()->id;
            $query->where(fn ($q) => $q->where('assignee_id', $id)->orWhere('reporter_id', $id)->orWhere('tester_id', $id)->when($request->user()->role === 'tester', fn ($q) => $q->orWhere('status', 'qa')));
        }
        $issues = $query->with(['project', 'assignee'])->withSum('worklogs', 'hours')->get();
        $active = $issues->whereNotIn('status', Issue::FINAL);
        $events = AuditEvent::whereIn('issue_id', $issues->pluck('id'))->with(['user', 'issue'])->latest('id')->limit(12)->get();
        $queue = $active->where('status', 'qa');
        $waitingHours = $queue->map(function ($issue) {
            if (! $issue->qa_queued_at) {
                return 0;
            }

            return app(BusinessCalendar::class)->waitingHours($issue->qa_queued_at, now());
        });

        return response()->json(['active' => $active->count(), 'statuses' => $active->countBy('status'),
            'unassigned' => $active->whereNull('assignee_id')->count(), 'unestimated' => $active->whereNull('remaining')->count(),
            'overdue' => $active->filter(fn ($i) => $i->due_date?->lt(today()))->count(),
            'blocked' => $active->where('status', 'blocked')->count(), 'events' => $events,
            'qa_wait_days' => round(($waitingHours->avg() ?? 0) / 8, 1),
            'estimate' => $issues->sum('estimate'), 'spent' => round($issues->sum('worklogs_sum_hours'), 1), 'remaining' => $active->sum('remaining'),
            'projects' => $issues->groupBy('project_id')->map(fn ($items) => ['project' => $items->first()->project, 'total' => $items->count(),
                'closed' => $items->where('status', 'closed')->count(), 'active' => $items->whereNotIn('status', Issue::FINAL)->count(),
                'qa' => $items->whereIn('status', ['qa', 'testing'])->count(),
                'overdue' => $items->whereNotIn('status', Issue::FINAL)->filter(fn ($i) => $i->due_date?->lt(today()))->count(),
                'estimate' => $items->sum('estimate'), 'spent' => round($items->sum('worklogs_sum_hours'), 1), 'remaining' => $items->whereNotIn('status', Issue::FINAL)->sum('remaining')])->values()]);
    }

    public function workload(Request $request, Workload $workload): JsonResponse
    {
        abort_unless($request->user()->hasPermission('reports') && in_array($request->user()->role, ['admin', 'manager']), 403);
        $data = $request->validate(['period' => ['nullable', Rule::in(['today', 'week', 'next', 'fortnight', 'month', 'custom'])],
            'from' => 'required_if:period,custom|nullable|date', 'to' => 'required_if:period,custom|nullable|date|after_or_equal:from|before_or_equal:'.now()->addYears(2)->toDateString(),
            'team_id' => 'nullable|integer|exists:teams,id']);
        [$from, $to] = $workload->period($data['period'] ?? 'week', $data['from'] ?? null, $data['to'] ?? null);
        abort_if($from->diffInDays($to) > 366, 422, 'Максимальный период: один год.');

        return response()->json($workload->report($request->user(), $from, $to, $data['team_id'] ?? null));
    }

    private function notificationsQuery(Request $request): Builder
    {
        return InboxNotification::where('user_id', $request->user()->id)->where(fn ($q) => $q->whereNull('issue_id')->orWhereHas('issue', fn ($i) => $i->visibleTo($request->user())));
    }

    public function notifications(Request $request): JsonResponse
    {
        return response()->json($this->notificationsQuery($request)->with('issue')->latest()->paginate(50));
    }

    public function readNotifications(Request $request): JsonResponse
    {
        $request->validate(['id' => 'nullable|integer']);
        $this->notificationsQuery($request)->when($request->filled('id'), fn ($q) => $q->where('id', $request->integer('id')))->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $data = $request->validate(['preferences' => 'required|array:created,updated,status_changed,commented,work_logged,file_uploaded,due_soon,overdue', 'preferences.*' => 'boolean']);
        $request->user()->update(['notification_preferences' => $data['preferences']]);

        return response()->json(['ok' => true]);
    }

    public function saveFilter(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:80', 'filters' => 'required|array:q,project_id,status,assignee_id,reporter_id,tester_id,priority,type,mine,qa,overdue,unassigned,unestimated,blocked,department,tag,due_before', 'filters.*' => 'nullable|string|max:200']);

        return response()->json(SavedFilter::create([...$data, 'user_id' => $request->user()->id]), 201);
    }

    public function deleteFilter(Request $request, SavedFilter $filter): JsonResponse
    {
        abort_unless($filter->user_id === $request->user()->id, 404);
        $filter->delete();

        return response()->json(['ok' => true]);
    }
}
