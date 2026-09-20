<?php

namespace App\Services;

use App\Models\CalendarException;
use App\Models\Issue;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class Workload
{
    public function period(string $period, ?string $from = null, ?string $to = null): array
    {
        $today = CarbonImmutable::today();

        return match ($period) {
            'today' => [$today, $today],
            'next' => [$today->addWeek()->startOfWeek(), $today->addWeek()->endOfWeek()->startOfDay()],
            'fortnight' => [$today->startOfWeek(), $today->addWeek()->endOfWeek()->startOfDay()],
            'month' => [$today->startOfMonth(), $today->endOfMonth()->startOfDay()],
            'custom' => [CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->startOfDay()],
            default => [$today->startOfWeek(), $today->endOfWeek()->startOfDay()],
        };
    }

    public function capacity(User $user, CarbonImmutable $from, CarbonImmutable $to, Collection $exceptions): float
    {
        $days = $user->work_days ?? [1, 2, 3, 4, 5];
        $daily = count($days) ? $user->weekly_capacity / count($days) : 0;
        $dayCount = (int) $from->diffInDays($to) + 1;
        $total = intdiv($dayCount, 7) * count($days) * $daily;
        for ($offset = 0; $offset < $dayCount % 7; $offset++) {
            $total += in_array($from->addDays($offset)->isoWeekday(), $days, true) ? $daily : 0;
        }
        $applicable = $exceptions->filter(fn ($e) => ($e->user_id === null || $e->user_id === $user->id) && $e->date->gte($from) && $e->date->lte($to));
        foreach ($applicable->groupBy(fn ($e) => $e->date->toDateString()) as $overrides) {
            $exception = $overrides->firstWhere('user_id', $user->id) ?? $overrides->first(fn ($e) => $e->user_id === null);
            $usual = in_array($exception->date->isoWeekday(), $days, true) ? $daily : 0;
            $total += $exception->hours - $usual;
        }

        return round($total, 2);
    }

    public function report(User $viewer, CarbonImmutable $from, CarbonImmutable $to, ?int $teamId = null): array
    {
        $users = User::where('role', 'developer')->where('active', true)
            ->when($viewer->role !== 'admin', fn ($q) => $q->whereHas('teams', fn ($team) => $team->where('leader_id', $viewer->id)))
            ->when($teamId, fn ($q) => $q->whereHas('teams', fn ($team) => $team->where('teams.id', $teamId)))
            ->with('teams')->orderBy('name')->get();
        $exceptions = CalendarException::whereBetween('date', [$from->toDateString(), $to->toDateString()])->get();
        // Include other-project effort in totals without disclosing inaccessible task details.
        $allIssues = Issue::whereIn('assignee_id', $users->pluck('id'))->whereNotIn('status', Issue::FINAL)->with('project')->get();
        $visibleIds = $viewer->visibleProjectIds();
        $thresholds = Setting::read('thresholds', config('tracker.thresholds'));
        $calendarByPeriod = [];
        $rows = $users->map(function ($user) use ($from, $to, $exceptions, $allIssues, $visibleIds, $thresholds, &$calendarByPeriod) {
            $assigned = $allIssues->where('assignee_id', $user->id);
            $development = $assigned->whereIn('status', Issue::PLANNABLE);
            $missingPlan = $development->filter(fn ($issue) => ! $issue->planned_start || ! $issue->planned_end
                || ($issue->planned_end->lt($from) && $issue->planned_end->lt(today()) && ($issue->remaining === null || $issue->remaining > 0)));
            $planned = $development->filter(fn ($issue) => $issue->planned_start && $issue->planned_end && $issue->planned_start->lte($to) && $issue->planned_end->gte($from));
            $unestimated = $planned->filter(fn ($issue) => $issue->remaining === null)->count();
            $hours = 0;
            foreach ($planned as $issue) {
                $start = CarbonImmutable::instance($issue->planned_start);
                $end = CarbonImmutable::instance($issue->planned_end);
                $periodKey = $start->toDateString().'/'.$end->toDateString();
                $fullExceptions = $calendarByPeriod[$periodKey] ??= CalendarException::whereBetween('date', [$start->toDateString(), $end->toDateString()])->get();
                $fullCapacity = $this->capacity($user, $start, $end, $fullExceptions);
                $overlap = $this->capacity($user, $start->max($from), $end->min($to), $exceptions);
                $hours += ($issue->remaining ?? 0) * ($fullCapacity > 0 ? $overlap / $fullCapacity : 1);
            }
            $capacity = $this->capacity($user, $from, $to, $exceptions);
            $incomplete = $unestimated > 0 || $missingPlan->isNotEmpty();
            $rawPercent = $capacity > 0 ? $hours / $capacity * 100 : null;
            $percent = $incomplete || $rawPercent === null ? null : round($rawPercent, 1);
            $overloaded = $rawPercent === null ? $hours > 0 : $rawPercent > $thresholds['overload'];

            return [
                'user' => $user, 'hours' => round($hours, 1), 'capacity' => $capacity, 'percent' => $percent,
                'state' => $overloaded ? 'overload' : ($incomplete ? 'incomplete' : ($capacity <= 0 ? 'unavailable' : ($rawPercent < $thresholds['reserve'] ? 'reserve' : 'normal'))),
                'unestimated' => $development->filter(fn ($issue) => $issue->remaining === null)->count(),
                'unplanned' => $missingPlan->count(), 'incomplete' => $incomplete,
                'wip' => $assigned->where('status', 'development')->count(), 'active' => $assigned->count(),
                'overdue' => $assigned->filter(fn ($issue) => $issue->due_date?->lt(today()))->count(),
                'blocked' => $assigned->where('status', 'blocked')->count(),
                'returned' => $assigned->where('status', 'returned')->count(),
                'issues' => $assigned->filter(fn ($issue) => $visibleIds->contains($issue->project_id))->values(),
                'hidden_tasks' => $assigned->filter(fn ($issue) => ! $visibleIds->contains($issue->project_id))->count(),
            ];
        });

        return ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'rows' => $rows, 'thresholds' => $thresholds,
            'hours' => round($rows->sum('hours'), 1), 'capacity' => $rows->sum('capacity'),
            'incomplete' => $rows->contains('incomplete', true),
            'overloaded' => $rows->where('state', 'overload')->count(), 'available' => $rows->where('state', 'reserve')->count()];
    }
}
