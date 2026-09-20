<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\CalendarException;
use App\Models\InboxNotification;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TrackerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $reporter;

    private User $developer;

    private User $tester;

    private User $observer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-21 12:00:00', 'Asia/Almaty'));
        foreach (['manager', 'reporter', 'developer', 'tester', 'observer'] as $role) {
            $this->{$role} = User::factory()->create(['role' => $role, 'active' => true, 'weekly_capacity' => 40, 'work_days' => [1, 2, 3, 4, 5]]);
        }
        $team = Team::create(['name' => 'Team', 'leader_id' => $this->manager->id]);
        $team->members()->sync([$this->manager->id, $this->reporter->id, $this->developer->id, $this->tester->id, $this->observer->id]);
        $this->project = Project::create(['name' => 'Enbek', 'key' => 'ENBEK', 'team_id' => $team->id, 'owner_id' => $this->manager->id]);
        $this->project->members()->sync($team->members->pluck('id'));
    }

    private function issue(array $data = []): Issue
    {
        $issue = Issue::create(['project_id' => $this->project->id, 'reporter_id' => $this->reporter->id, 'assignee_id' => $this->developer->id,
            'tester_id' => $this->tester->id, 'title' => 'Test task', 'description' => 'Requirements', 'status' => 'ready', 'estimate' => 40, 'remaining' => 32,
            'planned_start' => '2026-09-21', 'planned_end' => '2026-09-25', ...$data]);
        $issue->update(['key' => 'ENBEK-'.$issue->id]);

        return $issue;
    }

    public function test_complete_workflow_with_qa_return_and_acceptance(): void
    {
        $created = $this->actingAs($this->reporter)->postJson('/api/v1/issues', ['project_id' => $this->project->id, 'title' => 'Transfer employer', 'description' => 'Acceptance criteria'])->assertCreated();
        $id = $created->json('data.id');
        $base = '/api/v1/issues/'.$id;
        $this->actingAs($this->manager)->patchJson($base, ['assignee_id' => $this->developer->id, 'tester_id' => $this->tester->id])->assertOk();
        $this->postJson($base.'/transitions', ['status' => 'ready'])->assertOk();
        $this->actingAs($this->developer)->getJson('/api/v1/issues?mine=1')->assertJsonPath('meta.total', 1);
        $this->postJson($base.'/transitions', ['status' => 'development'])->assertUnprocessable();
        $this->patchJson($base, ['estimate' => 16, 'remaining' => 16])->assertOk();
        $this->postJson($base.'/transitions', ['status' => 'development'])->assertOk();
        $this->postJson($base.'/worklogs', ['hours' => 2, 'date' => '2026-09-21', 'remaining' => 14, 'comment' => 'SQL fix'])->assertCreated();
        $this->postJson($base.'/transitions', ['status' => 'qa'])->assertOk();
        $this->actingAs($this->tester)->postJson($base.'/transitions', ['status' => 'testing'])->assertOk();
        $this->postJson($base.'/transitions', ['status' => 'returned'])->assertUnprocessable();
        $this->assertSame('testing', Issue::find($id)->status);
        $this->postJson($base.'/transitions', ['status' => 'returned', 'reason' => 'Invalid BIN validation', 'test_result' => ['expected' => 'Accept leading zero', 'actual' => 'Error']])->assertOk()->assertJsonPath('data.return_count', 1);
        $this->actingAs($this->developer)->postJson($base.'/transitions', ['status' => 'development'])->assertOk();
        $this->postJson($base.'/transitions', ['status' => 'qa'])->assertOk();
        $this->actingAs($this->tester)->postJson($base.'/transitions', ['status' => 'testing'])->assertOk();
        $this->postJson($base.'/transitions', ['status' => 'acceptance'])->assertOk();
        $this->actingAs($this->reporter)->postJson($base.'/transitions', ['status' => 'closed'])->assertOk()->assertJsonPath('data.return_count', 1);
        $this->assertSame(10, AuditEvent::where('issue_id', $id)->where('action', 'status_changed')->count());
        $this->assertDatabaseHas('comments', ['issue_id' => $id, 'body' => 'Invalid BIN validation']);
        $this->assertDatabaseHas('worklogs', ['issue_id' => $id, 'hours' => 2]);
    }

    public function test_project_boundaries_and_roles_are_enforced_on_server(): void
    {
        $issue = $this->issue();
        $outsider = User::factory()->create(['role' => 'developer']);
        $this->actingAs($outsider)->getJson('/api/v1/issues/'.$issue->id)->assertNotFound();
        $this->getJson('/api/v1/issues')->assertJsonPath('meta.total', 0);
        $this->postJson('/api/v1/issues/'.$issue->id.'/comments', ['body' => 'Hello'])->assertNotFound();
        $this->actingAs($this->reporter)->patchJson('/api/v1/issues/'.$issue->id, ['estimate' => 2])->assertForbidden();
        $this->actingAs($this->developer)->patchJson('/api/v1/issues/'.$issue->id, ['assignee_id' => $outsider->id])->assertForbidden();
        $this->actingAs($this->developer)->postJson('/api/v1/issues/'.$issue->id.'/transitions', ['status' => 'closed'])->assertForbidden();
        $this->actingAs($this->observer)->postJson('/api/v1/issues/'.$issue->id.'/comments', ['body' => 'Not allowed'])->assertForbidden();
        $this->actingAs($this->tester)->getJson('/api/v1/reports/workload')->assertForbidden();
        $this->actingAs($this->manager)->getJson('/api/v1/admin')->assertForbidden();
    }

    public function test_inactive_user_is_rejected_even_with_existing_session(): void
    {
        $this->developer->update(['active' => false]);
        $this->actingAs($this->developer)->getJson('/api/v1/bootstrap')->assertForbidden();
    }

    public function test_workload_uses_hours_and_suppresses_unknown_percent(): void
    {
        $this->issue();
        $url = '/api/v1/reports/workload?period=week';
        $this->actingAs($this->manager)->getJson($url)->assertOk()->assertJsonPath('rows.0.hours', 32)->assertJsonPath('rows.0.capacity', 40)->assertJsonPath('rows.0.percent', 80);
        $unknown = $this->issue(['remaining' => null, 'estimate' => null]);
        $this->getJson($url)->assertOk()->assertJsonPath('rows.0.percent', null)->assertJsonPath('rows.0.unestimated', 1)->assertJsonPath('incomplete', true);
        $unknown->update(['remaining' => 2, 'planned_start' => null, 'planned_end' => null]);
        $this->getJson($url)->assertJsonPath('rows.0.percent', null)->assertJsonPath('rows.0.unplanned', 1);
    }

    public function test_capacity_respects_calendar_and_next_week_planning(): void
    {
        $this->issue(['remaining' => 40, 'planned_end' => '2026-10-02']);
        $this->actingAs($this->manager)->getJson('/api/v1/reports/workload?period=week')->assertJsonPath('rows.0.hours', 20);
        $this->getJson('/api/v1/reports/workload?period=next')->assertJsonPath('rows.0.hours', 20);
        CalendarException::create(['user_id' => $this->developer->id, 'date' => '2026-09-21', 'hours' => 0, 'reason' => 'Leave']);
        $this->getJson('/api/v1/reports/workload?period=week')->assertJsonPath('rows.0.capacity', 32);
        $this->developer->update(['weekly_capacity' => 0]);
        $this->getJson('/api/v1/reports/workload?period=week')->assertJsonPath('rows.0.percent', null)->assertJsonPath('rows.0.state', 'overload');
        $this->getJson('/api/v1/reports/workload?period=custom&from=2026-09-25&to=2026-09-21')->assertUnprocessable();
    }

    public function test_other_project_effort_is_counted_without_disclosing_details(): void
    {
        $otherManager = User::factory()->create(['role' => 'manager']);
        $otherTeam = Team::create(['name' => 'Private team', 'leader_id' => $otherManager->id]);
        $project = Project::create(['name' => 'Private', 'key' => 'PRV', 'team_id' => $otherTeam->id, 'owner_id' => $otherManager->id]);
        $this->issue(['project_id' => $project->id, 'title' => 'Private secret task']);
        $this->actingAs($this->manager)->getJson('/api/v1/reports/workload?period=week')->assertJsonPath('rows.0.hours', 32)->assertJsonPath('rows.0.hidden_tasks', 1)->assertJsonPath('rows.0.issues', [])->assertDontSee('Private secret task');
    }

    public function test_upload_validation_and_private_download(): void
    {
        Storage::fake('local');
        $issue = $this->issue();
        $base = '/api/v1/issues/'.$issue->id;
        $upload = $this->actingAs($this->developer)->postJson($base.'/attachments', ['file' => UploadedFile::fake()->createWithContent('notes.txt', 'Test notes')])->assertCreated();
        $id = $upload->json('id');
        $this->get('/api/v1/attachments/'.$id)->assertOk()->assertHeader('content-type', 'application/octet-stream');
        $this->postJson($base.'/attachments', ['file' => UploadedFile::fake()->createWithContent('payload.php', '<?php echo 1;')])->assertUnprocessable();
        $this->postJson($base.'/attachments', ['file' => UploadedFile::fake()->createWithContent('payload.svg', '<svg onload="alert(1)"></svg>')])->assertUnprocessable();
        $this->postJson($base.'/attachments', ['file' => UploadedFile::fake()->create('big.pdf', 21000, 'application/pdf')])->assertUnprocessable();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get('/api/v1/attachments/'.$id)->assertNotFound();
    }

    public function test_worklogs_validate_date_hours_and_remaining_without_automatic_subtraction(): void
    {
        $issue = $this->issue();
        $url = '/api/v1/issues/'.$issue->id.'/worklogs';
        $this->actingAs($this->developer)->postJson($url, ['hours' => -1, 'date' => '2026-09-21', 'remaining' => 1])->assertUnprocessable();
        $this->postJson($url, ['hours' => 2, 'date' => '2026-09-22', 'remaining' => 1])->assertUnprocessable();
        $this->postJson($url, ['hours' => 2, 'date' => '2026-09-21', 'remaining' => 40])->assertCreated();
        $this->assertEquals(40, $issue->fresh()->remaining);
    }

    public function test_closed_parent_requires_completed_children(): void
    {
        $parent = $this->issue(['status' => 'acceptance']);
        $child = $this->issue(['parent_id' => $parent->id]);
        $this->actingAs($this->reporter)->postJson('/api/v1/issues/'.$parent->id.'/transitions', ['status' => 'closed'])->assertUnprocessable();
        $child->update(['status' => 'closed']);
        $this->postJson('/api/v1/issues/'.$parent->id.'/transitions', ['status' => 'closed'])->assertOk();
    }

    public function test_comment_search_and_notifications_do_not_leak_project_data(): void
    {
        $issue = $this->issue();
        $this->actingAs($this->developer)->postJson('/api/v1/issues/'.$issue->id.'/comments', ['body' => 'UniqueCommentNeedle'])->assertCreated();
        $this->actingAs($this->reporter)->getJson('/api/v1/issues?q=UniqueCommentNeedle')->assertJsonPath('meta.total', 1);
        $this->assertDatabaseHas('inbox_notifications', ['user_id' => $this->reporter->id, 'issue_id' => $issue->id, 'type' => 'commented']);
        $this->project->members()->detach($this->reporter->id);
        $this->getJson('/api/v1/notifications')->assertJsonPath('total', 0);
    }

    public function test_overdue_excludes_final_statuses_and_reminders_are_idempotent(): void
    {
        $this->issue(['due_date' => '2026-09-20']);
        $this->issue(['status' => 'closed', 'due_date' => '2026-09-20']);
        $this->actingAs($this->manager)->getJson('/api/v1/issues?overdue=1')->assertJsonPath('meta.total', 1);
        $this->artisan('issues:notify-due')->assertSuccessful();
        $count = InboxNotification::where('type', 'overdue')->count();
        $this->assertSame(3, $count);
        $this->artisan('issues:notify-due')->assertSuccessful();
        $this->assertSame($count, InboxNotification::where('type', 'overdue')->count());
    }

    public function test_audit_entries_cannot_be_edited_and_admin_routes_are_protected(): void
    {
        $event = AuditEvent::create(['user_id' => $this->manager->id, 'action' => 'login']);
        $this->actingAs($this->developer)->putJson('/api/v1/settings', ['thresholds' => ['reserve' => 80, 'overload' => 100]])->assertForbidden();
        $this->expectException(\LogicException::class);
        $event->update(['action' => 'modified']);
    }

    public function test_custom_workflow_still_respects_role_permissions(): void
    {
        $issue = $this->issue();
        $this->project->update(['workflow' => ['ready' => ['closed']]]);
        $this->actingAs($this->developer)->postJson('/api/v1/issues/'.$issue->id.'/transitions', ['status' => 'closed'])->assertForbidden();
    }

    public function test_cyrillic_full_text_index_tracks_comments_users_and_title_changes(): void
    {
        $issue = $this->issue(['title' => 'Перенос предприятия']);
        $this->actingAs($this->manager)->getJson('/api/v1/issues?q='.urlencode('перенос'))->assertJsonPath('meta.total', 1);
        $issue->update(['title' => 'Обновление кабинета']);
        $this->getJson('/api/v1/issues?q='.urlencode('перенос'))->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/issues?q='.urlencode('ОБНОВЛЕНИЕ'))->assertJsonPath('meta.total', 1);
        $this->developer->update(['name' => 'Иван Сидоров']);
        $this->getJson('/api/v1/issues?q='.urlencode('сидоров'))->assertJsonPath('meta.total', 1);
        $this->postJson('/api/v1/issues/'.$issue->id.'/comments', ['body' => 'Проверить сертификат'])->assertCreated();
        $this->getJson('/api/v1/issues?q='.urlencode('сертификат'))->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/issues?q='.urlencode('" OR 1=1 --'))->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_dashboard_reports_queue_and_timing(): void
    {
        $this->issue(['status' => 'qa', 'qa_queued_at' => '2026-09-18 09:00:00']);
        $this->actingAs($this->manager)->getJson('/api/v1/reports/dashboard')->assertOk()->assertJsonPath('statuses.qa', 1)->assertJsonPath('qa_wait_days', 1.4);
    }

    public function test_known_overload_remains_visible_when_other_tasks_have_no_estimate(): void
    {
        $this->issue(['remaining' => 52]);
        $this->issue(['remaining' => null]);
        $this->actingAs($this->manager)->getJson('/api/v1/reports/workload?period=week')
            ->assertJsonPath('rows.0.percent', null)->assertJsonPath('rows.0.state', 'overload')->assertJsonPath('overloaded', 1);
    }

    public function test_unfinished_work_with_expired_plan_does_not_appear_as_free_capacity(): void
    {
        $this->issue(['planned_start' => '2026-09-14', 'planned_end' => '2026-09-18']);
        $this->actingAs($this->manager)->getJson('/api/v1/reports/workload?period=week')
            ->assertJsonPath('rows.0.percent', null)->assertJsonPath('rows.0.unplanned', 1)->assertJsonPath('available', 0);
    }

    public function test_audit_creation_uses_application_time(): void
    {
        $event = AuditEvent::create(['user_id' => $this->manager->id, 'action' => 'login']);
        $this->assertSame(now()->format('Y-m-d H:i:s'), $event->fresh()->created_at->format('Y-m-d H:i:s'));
    }
}
