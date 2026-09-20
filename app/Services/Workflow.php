<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Workflow
{
    public function allowed(User $user, Issue $issue): array
    {
        if (! $user->active || ! $user->visibleProjectIds()->contains($issue->project_id)) {
            return [];
        }
        $graph = $issue->project->workflow ?? config('tracker.workflow');

        return array_values(array_filter($graph[$issue->status] ?? [], function ($target) use ($user, $issue) {
            if ($user->manages($issue->project)) {
                return true;
            }
            if (in_array($issue->status, Issue::FINAL, true)) {
                return $user->hasPermission('reopen') && $issue->reporter_id === $user->id;
            }
            if ($issue->status === 'acceptance') {
                return $user->hasPermission('accept') && $issue->reporter_id === $user->id && in_array($target, ['closed', 'returned'], true);
            }
            if (in_array($issue->status, ['qa', 'testing'], true)) {
                return $user->hasPermission('test') && ($issue->tester_id === null || $issue->tester_id === $user->id) && in_array($target, ['testing', 'returned', 'acceptance'], true);
            }

            return $user->hasPermission('develop') && $issue->assignee_id === $user->id && in_array($issue->status, Issue::DEVELOPMENT, true) && in_array($target, ['development', 'qa', 'blocked', 'ready', 'clarification'], true);
        }));
    }

    public function transition(User $user, Issue $issue, string $target, ?string $reason, array $testResult = [], ?float $estimate = null): Issue
    {
        return DB::transaction(function () use ($user, $issue, $target, $reason, $testResult, $estimate) {
            $issue = Issue::lockForUpdate()->findOrFail($issue->id);
            abort_unless(in_array($target, $this->allowed($user, $issue), true), 403, 'Переход статуса недоступен.');
            if ($estimate !== null) {
                abort_unless($target === 'development' && ($user->manages($issue->project) || ($user->hasPermission('estimate') && $issue->assignee_id === $user->id)), 403);
                $changes = [];
                foreach (['estimate', 'remaining'] as $field) {
                    if ($issue->{$field} === null) {
                        $changes[$field] = ['from' => null, 'to' => $estimate];
                        $issue->{$field} = $estimate;
                    }
                }
                if ($changes) {
                    app(IssueActivity::class)->record($user, $issue, 'updated', $changes);
                }
            }
            if (in_array($target, ['returned', 'blocked'], true) && trim($reason ?? '') === '') {
                throw ValidationException::withMessages(['reason' => 'Укажите причину возврата или блокировки.']);
            }
            if ($target === 'development' && (! $issue->assignee_id || $issue->estimate === null || $issue->remaining === null)) {
                throw ValidationException::withMessages(['status' => 'Назначьте исполнителя и укажите первоначальную и остаточную оценки.']);
            }
            if ($target === 'closed' && $issue->children()->whereNotIn('status', Issue::FINAL)->exists()) {
                throw ValidationException::withMessages(['status' => 'Сначала завершите подзадачи.']);
            }
            $from = $issue->status;
            $issue->status = $target;
            $issue->block_reason = $target === 'blocked' ? $reason : null;
            if ($target === 'returned') {
                $issue->return_count++;
            }
            if ($target === 'qa') {
                $issue->qa_queued_at = now();
                $issue->qa_started_at = null;
            }
            if ($target === 'testing') {
                $issue->qa_started_at = now();
                $issue->tester_id ??= $user->id;
            }
            $issue->closed_at = in_array($target, Issue::FINAL, true) ? now() : null;
            $issue->save();
            if (trim($reason ?? '') !== '') {
                $issue->comments()->create(['user_id' => $user->id, 'body' => $reason, 'test_result' => $testResult ?: null]);
            }
            app(IssueActivity::class)->record($user, $issue, 'status_changed', ['status' => ['from' => $from, 'to' => $target], 'reason' => $reason]);

            return $issue;
        });
    }
}
