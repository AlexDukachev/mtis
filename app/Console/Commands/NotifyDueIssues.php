<?php

namespace App\Console\Commands;

use App\Models\InboxNotification;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Console\Command;

class NotifyDueIssues extends Command
{
    protected $signature = 'issues:notify-due';

    protected $description = 'Send internal notifications for upcoming and overdue deadlines';

    public function handle(): int
    {
        Issue::whereNotIn('status', Issue::FINAL)->whereDate('due_date', '<=', today()->addDays(2))->chunkById(100, function ($issues) {
            foreach ($issues as $issue) {
                $type = $issue->due_date->lt(today()) ? 'overdue' : 'due_soon';
                foreach (User::whereIn('id', array_filter([$issue->reporter_id, $issue->assignee_id, $issue->tester_id]))->where('active', true)->get() as $user) {
                    if (! $user->visibleProjectIds()->contains($issue->project_id) || ($user->notification_preferences[$type] ?? true) === false) {
                        continue;
                    }
                    if (! InboxNotification::where('user_id', $user->id)->where('issue_id', $issue->id)->where('type', $type)->whereDate('created_at', today())->exists()) {
                        InboxNotification::create(['user_id' => $user->id, 'issue_id' => $issue->id, 'type' => $type, 'title' => $issue->key.': '.($type === 'overdue' ? 'срок истек' : 'приближается срок')]);
                    }
                }
            }
        });
        $this->info('Deadline notifications updated.');

        return self::SUCCESS;
    }
}
