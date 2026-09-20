<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\RoadmapItem;
use App\Services\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoadmapController extends Controller
{
    public function index(Request $request, BusinessCalendar $calendar): JsonResponse
    {
        $data = $request->validate(['year' => 'nullable|integer|min:2020|max:2100', 'project_id' => 'nullable|integer', 'page' => 'nullable|integer|min:1']);
        $year = $data['year'] ?? now()->year;
        $from = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $to = $from->endOfYear();
        $ids = $request->user()->visibleProjectIds();
        // Include work whose deadline is later but preparation already overlaps this year.
        $all = RoadmapItem::whereIn('project_id', $ids)->whereDate('deadline', '>=', $from)->whereDate('deadline', '<=', $to->addYears(3))
            ->with(['project.team', 'developers'])->orderBy('deadline')->get();
        foreach ($all as $item) {
            $item->setAttribute('latest_start', $calendar->latestStart($item->deadline->toDateString(), $item->duration_days)->toDateString());
        }
        $visible = $all->filter(fn ($item) => $item->latest_start <= $to->toDateString()
            && ($request->boolean('include_completed') || ! in_array($item->status, ['done', 'cancelled'], true))
            && (! isset($data['project_id']) || $item->project_id == $data['project_id']));
        $rows = $visible->map(function ($item) use ($request, $all) {
            $risks = [];
            $active = ! in_array($item->status, ['done', 'cancelled'], true);
            $fte = $item->developers->where('active', true)->sum(fn ($u) => min(1, $u->weekly_capacity / 40));
            if ($active && $item->deadline->lt(today())) {
                $risks[] = 'Срок сдачи пропущен';
            } elseif ($active && $item->status === 'planned' && $item->latest_start < today()->toDateString()) {
                $risks[] = 'Плановый старт пропущен';
            } elseif ($active && $item->status === 'planned' && $item->latest_start <= today()->addWeeks(2)->toDateString()) {
                $risks[] = 'Начать в ближайшие 2 недели';
            }
            if ($active && $fte < $item->required_people) {
                $risks[] = 'Не хватает '.round($item->required_people - $fte, 1).' штатных единиц';
            }
            $conflicts = $active ? $all->filter(fn ($other) => $other->id !== $item->id && ! in_array($other->status, ['done', 'cancelled'], true)
                && $other->latest_start <= $item->deadline->toDateString() && $other->deadline->toDateString() >= $item->latest_start
                && $other->developers->pluck('id')->intersect($item->developers->pluck('id'))->isNotEmpty())->count() : 0;
            if ($conflicts) {
                $risks[] = 'Пересечение исполнителей: '.$conflicts.' работ';
            }

            return [...$item->toArray(), 'risks' => $risks, 'assigned_fte' => round($fte, 1),
                'planned_hours' => $item->duration_days * 8 * $item->required_people,
                'can_edit' => $request->user()->manages($item->project)];
        })->values();
        $page = (int) ($data['page'] ?? 1);

        return response()->json(['data' => $rows->forPage($page, 25)->values(), 'total' => $rows->count(), 'last_page' => max(1, (int) ceil($rows->count() / 25)),
            'at_risk' => $rows->filter(fn ($r) => count($r['risks']) > 0)->count(), 'year' => $year]);
    }

    public function save(Request $request, ?RoadmapItem $roadmapItem = null): JsonResponse
    {
        $data = $request->validate([
            'project_id' => 'required|integer|exists:projects,id', 'title' => 'required|string|max:255', 'description' => 'nullable|string|max:20000',
            'deadline' => 'required|date|after_or_equal:2020-01-01|before_or_equal:2100-12-31', 'duration_days' => 'required|integer|min:1|max:520',
            'required_people' => 'required|integer|min:1|max:100', 'status' => ['required', Rule::in(['planned', 'in_progress', 'done', 'cancelled'])],
            'developer_ids' => 'present|array|max:100', 'developer_ids.*' => 'integer|distinct|exists:users,id',
        ]);
        $project = Project::findOrFail($data['project_id']);
        abort_unless($request->user()->visibleProjectIds()->contains($project->id) && $request->user()->manages($project), 403);
        if ($roadmapItem) {
            abort_unless($roadmapItem->project_id === $project->id, 422, 'Проект существующей работы нельзя изменить.');
        }
        $ids = $data['developer_ids'];
        abort_unless($project->members()->whereIn('users.id', $ids)->where('role', 'developer')->where('active', true)->count() === count($ids), 422, 'Выберите активных разработчиков проекта.');
        unset($data['developer_ids']);
        $created = $roadmapItem === null;
        $item = DB::transaction(function () use ($request, $roadmapItem, $data, $ids) {
            $item = $roadmapItem ? RoadmapItem::lockForUpdate()->findOrFail($roadmapItem->id) : new RoadmapItem;
            $before = $item->exists ? [...$item->toArray(), 'developer_ids' => $item->developers()->pluck('users.id')->all()] : null;
            $item->fill($data)->save();
            $item->developers()->sync($ids);
            AuditEvent::create(['user_id' => $request->user()->id, 'action' => 'roadmap_saved', 'changes' => ['roadmap_id' => $item->id, 'before' => $before, 'after' => [...$data, 'developer_ids' => $ids]]]);

            return $item;
        });

        return response()->json($item->load('developers'), $created ? 201 : 200);
    }
}
