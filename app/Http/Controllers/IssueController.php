<?php

namespace App\Http\Controllers;

use App\Http\Resources\IssueResource;
use App\Models\Attachment;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Models\Worklog;
use App\Services\IssueActivity;
use App\Services\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IssueController extends Controller
{
    public function collection(Request $request, Issue $issue, string $collection): JsonResponse
    {
        $this->visible($request, $issue);
        abort_unless(in_array($collection, ['comments', 'worklogs', 'attachments'], true), 404);

        return response()->json(['data' => $issue->{$collection}()->get()]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['q' => 'nullable|string|max:200', 'per_page' => 'nullable|integer|min:1|max:100', 'status' => ['nullable', Rule::in(array_keys(config('tracker.statuses')))]]);
        $query = Issue::visibleTo($request->user())->with(['project.team', 'assignee', 'reporter', 'tester'])->withSum('worklogs', 'hours')->withCount(['comments', 'attachments']);
        foreach (['project_id', 'status', 'assignee_id', 'reporter_id', 'tester_id', 'priority', 'type'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->boolean('mine')) {
            $id = $request->user()->id;
            $query->where(fn ($q) => $q->where('assignee_id', $id)->orWhere('reporter_id', $id)->orWhere('tester_id', $id));
        }
        if ($request->boolean('qa')) {
            $query->whereIn('status', ['qa', 'testing']);
        }
        if ($request->boolean('overdue')) {
            $query->whereDate('due_date', '<', today())->whereNotIn('status', Issue::FINAL);
        }
        if ($request->boolean('unassigned')) {
            $query->whereNull('assignee_id')->whereNotIn('status', Issue::FINAL);
        }
        if ($request->boolean('unestimated')) {
            $query->whereNull('remaining')->whereNotIn('status', Issue::FINAL);
        }
        if ($request->boolean('blocked')) {
            $query->where('status', 'blocked');
        }
        if ($request->filled('department')) {
            $query->whereHas('assignee', fn ($q) => $q->where('department', $request->input('department')));
        }
        if ($request->filled('tag')) {
            $query->whereJsonContains('tags', $request->input('tag'));
        }
        if ($request->filled('due_before')) {
            $request->validate(['due_before' => 'date']);
            $query->whereDate('due_date', '<=', $request->input('due_before'));
        }
        if ($request->filled('q')) {
            $term = (string) $request->string('q')->trim();
            if (DB::getDriverName() === 'sqlite') {
                $words = preg_split('/[^\\p{L}\\p{N}_]+/u', $term, -1, PREG_SPLIT_NO_EMPTY);
                $search = implode(' AND ', array_map(fn ($word) => '"'.$word.'"*', $words));
                $search === '' ? $query->whereRaw('1 = 0') : $query->whereRaw('issues.id IN (SELECT rowid FROM issue_search WHERE issue_search MATCH ?)', [$search]);
            } else {
                $query->where(fn ($q) => $q->whereFullText(['key', 'title', 'description'], $term)
                    ->orWhereHas('comments', fn ($c) => $c->whereFullText('body', $term))
                    ->orWhereHas('assignee', fn ($u) => $u->whereFullText(['name', 'username'], $term)));
            }
        }

        return IssueResource::collection($query->orderByDesc('updated_at')->paginate($request->integer('per_page', 50)));
    }

    public function show(Request $request, Issue $issue): IssueResource
    {
        $this->visible($request, $issue);

        return $this->resource($issue);
    }

    public function store(Request $request): IssueResource
    {
        abort_unless($request->user()->hasPermission('create'), 403);
        $data = $request->validate($this->rules(true));
        $project = Project::findOrFail($data['project_id']);
        abort_unless($request->user()->visibleProjectIds()->contains($project->id), 403);
        abort_unless($project->status === 'active', 422, 'Проект приостановлен.');
        $this->validateAssignments($request, $project, $data);
        if (isset($data['parent_id'])) {
            abort_unless(Issue::where('id', $data['parent_id'])->where('project_id', $project->id)->exists(), 422, 'Родительская задача должна быть в том же проекте.');
        }
        if (isset($data['estimate']) || isset($data['remaining'])) {
            abort_unless($request->user()->manages($project) || ($request->user()->hasPermission('estimate') && ($data['assignee_id'] ?? null) === $request->user()->id), 403);
        }

        return DB::transaction(function () use ($request, $data, $project) {
            $issue = Issue::create([...$data, 'reporter_id' => $request->user()->id, 'status' => 'new']);
            $issue->update(['key' => $project->key.'-'.$issue->id]);
            app(IssueActivity::class)->record($request->user(), $issue, 'created');

            return $this->resource($issue);
        });
    }

    public function update(Request $request, Issue $issue): IssueResource
    {
        $this->visible($request, $issue);
        $data = $request->validate($this->rules(false));

        return DB::transaction(function () use ($request, $issue, $data) {
            $issue = Issue::lockForUpdate()->findOrFail($issue->id);
            $user = $request->user();
            $manage = $user->manages($issue->project);
            abort_if(in_array($issue->status, Issue::FINAL, true), 422, 'Переоткройте задачу перед редактированием.');
            $assignmentFields = ['assignee_id', 'tester_id'];
            $estimateFields = ['estimate', 'remaining', 'solution'];
            $basicFields = array_diff(array_keys($data), [...$assignmentFields, ...$estimateFields]);
            if ($basicFields) {
                abort_unless($manage || ($user->id === $issue->reporter_id && $user->hasPermission('create')), 403);
            }
            if (array_intersect(array_keys($data), $estimateFields)) {
                abort_unless($manage || ($user->hasPermission('estimate') && $issue->assignee_id === $user->id), 403);
            }
            $this->validateAssignments($request, $issue->project, $data);
            if (array_key_exists('planned_start', $data) || array_key_exists('planned_end', $data)) {
                $start = $data['planned_start'] ?? $issue->planned_start?->toDateString();
                $end = $data['planned_end'] ?? $issue->planned_end?->toDateString();
                if ($start && $end && $end < $start) {
                    throw ValidationException::withMessages(['planned_end' => 'Окончание не может быть раньше начала.']);
                }
            }
            $changes = [];
            foreach ($data as $key => $value) {
                $before = $issue->getRawOriginal($key);
                if ($before != $value) {
                    $changes[$key] = ['from' => $before, 'to' => $value];
                }
            }
            $previousAssignee = $issue->assignee_id;
            $issue->fill($data)->save();
            if ($changes) {
                app(IssueActivity::class)->record($user, $issue, 'updated', $changes, [$previousAssignee]);
            }

            return $this->resource($issue);
        });
    }

    public function transition(Request $request, Issue $issue, Workflow $workflow): IssueResource
    {
        $this->visible($request, $issue);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(config('tracker.statuses')))], 'reason' => 'nullable|string|max:10000', 'estimate' => 'nullable|numeric|min:0|max:100000',
            'test_result' => 'nullable|array:expected,actual,steps,environment', 'test_result.*' => 'nullable|string|max:10000']);
        $issue = $workflow->transition($request->user(), $issue, $data['status'], $data['reason'] ?? null, $data['test_result'] ?? [], $data['estimate'] ?? null);

        return $this->resource($issue);
    }

    public function comment(Request $request, Issue $issue): JsonResponse
    {
        $this->visible($request, $issue);
        abort_unless($request->user()->hasPermission('comment'), 403);
        $data = $request->validate(['body' => 'required|string|max:20000', 'parent_id' => ['nullable', Rule::exists('comments', 'id')->where('issue_id', $issue->id)]]);
        $comment = DB::transaction(function () use ($request, $issue, $data) {
            $comment = $issue->comments()->create([...$data, 'user_id' => $request->user()->id]);
            preg_match_all('/@([a-zA-Z0-9_.-]+)/', $data['body'], $matches);
            $mentioned = User::whereIn('username', $matches[1])->pluck('id')->all();
            app(IssueActivity::class)->record($request->user(), $issue, 'commented', ['comment_id' => $comment->id], $mentioned);

            return $comment;
        });

        return response()->json($comment->load('user'), 201);
    }

    public function worklog(Request $request, Issue $issue): JsonResponse
    {
        $this->visible($request, $issue);
        abort_unless($request->user()->manages($issue->project) || ($request->user()->hasPermission('worklog') && $issue->assignee_id === $request->user()->id), 403);
        $data = $request->validate(['hours' => 'required|numeric|min:0.01|max:24', 'date' => 'required|date|before_or_equal:today', 'comment' => 'nullable|string|max:5000', 'remaining' => 'required|numeric|min:0|max:100000']);

        return DB::transaction(function () use ($request, $issue, $data) {
            $issue = Issue::lockForUpdate()->findOrFail($issue->id);
            abort_if(in_array($issue->status, Issue::FINAL, true), 422, 'Задача завершена.');
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $dailyTotal = Worklog::where('user_id', $request->user()->id)->whereDate('date', $data['date'])->sum('hours');
            if ($dailyTotal + $data['hours'] > 24) {
                throw ValidationException::withMessages(['hours' => 'Суммарные трудозатраты за день не могут превышать 24 часа.']);
            }
            $log = $issue->worklogs()->create(['user_id' => $request->user()->id, 'date' => $data['date'], 'hours' => $data['hours'], 'comment' => $data['comment'] ?? null]);
            $before = $issue->remaining;
            $issue->update(['remaining' => $data['remaining']]);
            app(IssueActivity::class)->record($request->user(), $issue, 'work_logged', ['hours' => $data['hours'], 'remaining' => ['from' => $before, 'to' => $data['remaining']]]);

            return response()->json($log, 201);
        });
    }

    public function upload(Request $request, Issue $issue): JsonResponse
    {
        $this->visible($request, $issue);
        abort_unless($request->user()->hasPermission('comment'), 403);
        $request->validate(['file' => ['required', 'file', 'max:'.config('tracker.max_file_kb'),
            'extensions:jpg,jpeg,png,gif,webp,pdf,txt,log,csv,doc,docx,xls,xlsx,zip,mp4,webm',
            'mimes:jpg,jpeg,png,gif,webp,pdf,txt,csv,doc,docx,xls,xlsx,zip,mp4,webm']]);
        $file = $request->file('file');
        $path = $file->store('attachments', 'local');
        try {
            return DB::transaction(function () use ($request, $issue, $file, $path) {
                $issue = Issue::lockForUpdate()->findOrFail($issue->id);
                abort_if($issue->attachments()->sum('size') + $file->getSize() > config('tracker.max_issue_bytes'), 422, 'Превышен объем вложений задачи (100 МБ).');
                $attachment = $issue->attachments()->create(['user_id' => $request->user()->id, 'name' => mb_substr(basename($file->getClientOriginalName()), 0, 250), 'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
                app(IssueActivity::class)->record($request->user(), $issue, 'file_uploaded', ['name' => $attachment->name]);

                return response()->json($attachment, 201);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->visible($request, $attachment->issue);

        return Storage::disk('local')->download($attachment->path, $attachment->name, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function visible(Request $request, Issue $issue): void
    {
        abort_unless($request->user()->visibleProjectIds()->contains($issue->project_id), 404);
    }

    private function resource(Issue $issue): IssueResource
    {
        return new IssueResource($issue->load(['project.team', 'assignee', 'reporter', 'tester', 'comments.user', 'worklogs.user', 'attachments', 'events.user', 'children'])->loadSum('worklogs', 'hours'));
    }

    private function rules(bool $creating): array
    {
        $rules = [
            'title' => ($creating ? 'required' : 'sometimes|required').'|string|max:255',
            'description' => ($creating ? 'required' : 'sometimes|required').'|string|max:50000',
            'type' => ['sometimes', Rule::in(array_keys(Setting::read('types', config('tracker.types'))))],
            'priority' => ['sometimes', Rule::in(array_keys(config('tracker.priorities')))],
            'assignee_id' => 'nullable|integer|exists:users,id', 'tester_id' => 'nullable|integer|exists:users,id',
            'due_date' => 'nullable|date', 'planned_start' => 'nullable|date', 'planned_end' => 'nullable|date|after_or_equal:planned_start',
            'estimate' => 'nullable|numeric|min:0|max:100000', 'remaining' => 'nullable|numeric|min:0|max:100000',
            'solution' => 'nullable|string|max:20000', 'component' => 'nullable|string|max:255', 'version' => 'nullable|string|max:255',
            'environment' => 'nullable|string|max:255', 'tags' => 'nullable|array|max:20', 'tags.*' => 'string|max:60',
        ];
        if ($creating) {
            $rules['project_id'] = 'required|integer|exists:projects,id';
            $rules['parent_id'] = 'nullable|integer|exists:issues,id';
        }

        return $rules;
    }

    private function validateAssignments(Request $request, Project $project, array $data): void
    {
        foreach (['assignee_id' => 'developer', 'tester_id' => 'tester'] as $field => $role) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            abort_unless($request->user()->manages($project) || $request->user()->hasPermission('assign'), 403, 'Назначение исполнителей недоступно.');
            if ($data[$field] !== null) {
                abort_unless($project->members()->where('users.id', $data[$field])->where('role', $role)->where('active', true)->exists(), 422, 'Исполнитель должен быть активным участником проекта с соответствующей ролью.');
            }
        }
    }
}
