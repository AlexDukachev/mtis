<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\InboxNotification;
use App\Models\Issue;
use App\Models\User;

class IssueActivity
{
    public function record(User $actor, Issue $issue, string $action, array $changes = [], array $extraRecipients = []): void
    {
        AuditEvent::create(['user_id' => $actor->id, 'issue_id' => $issue->id, 'action' => $action, 'changes' => $changes]);
        $ids = collect([$issue->reporter_id, $issue->assignee_id, $issue->tester_id, ...$extraRecipients])->filter()->unique()->reject(fn ($id) => $id === $actor->id);
        foreach (User::whereIn('id', $ids)->where('active', true)->get() as $recipient) {
            if (! $recipient->visibleProjectIds()->contains($issue->project_id) || ($recipient->notification_preferences[$action] ?? true) === false) {
                continue;
            }
            InboxNotification::create(['user_id' => $recipient->id, 'issue_id' => $issue->id, 'type' => $action, 'title' => $issue->key.': '.$action]);
        }
    }
}
