<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Issue;
use App\Models\Project;
use App\Models\RoadmapItem;
use App\Models\Team;
use App\Models\User;
use App\Services\BusinessCalendar;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkspaceImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private function project(User $manager, array $members): Project
    {
        $team = Team::create(['name' => 'Delivery', 'leader_id' => $manager->id]);
        $team->members()->sync([$manager->id, ...$members]);
        $project = Project::create(['name' => 'Enbek', 'key' => 'ENB'.Project::count(), 'owner_id' => $manager->id, 'team_id' => $team->id]);
        $project->members()->sync([$manager->id, ...$members]);

        return $project;
    }

    public function test_waiting_time_is_bounded_and_respects_working_hour_boundaries(): void
    {
        $calendar = new BusinessCalendar;
        $date = fn ($value) => CarbonImmutable::parse($value, config('app.timezone'));
        $this->assertEquals(1.0, $calendar->waitingHours($date('2026-09-18 16:30'), $date('2026-09-21 09:30')));
        $this->assertEquals(8.0, $calendar->waitingHours($date('2026-09-21 08:00'), $date('2026-09-21 18:00')));
        $this->assertEquals(0.0, $calendar->waitingHours($date('2026-09-19 08:00'), $date('2026-09-20 18:00')));
        $this->assertEquals(0.0, $calendar->waitingHours($date('2026-09-21 10:00'), $date('2026-09-21 09:00')));
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $this->assertGreaterThan(50000, $calendar->waitingHours($date('1990-01-01 00:00'), now()));
        }
        $this->assertLessThan(1, microtime(true) - $start);
        $this->assertSame('2026-09-14', $calendar->latestStart('2026-09-20', 5)->toDateString());
    }

    public function test_own_profile_can_change_without_access_to_privileged_fields(): void
    {
        $user = User::factory()->create(['role' => 'developer', 'weekly_capacity' => 40, 'password' => 'Original-Password-2026!']);
        $this->actingAs($user)->patchJson('/api/v1/profile', ['name' => 'Updated Name', 'theme' => 'dark', 'role' => 'admin', 'weekly_capacity' => 168])->assertOk();
        $this->assertSame('developer', $user->fresh()->role);
        $this->assertEquals(40, $user->fresh()->weekly_capacity);
        $this->assertSame('dark', $user->fresh()->theme);
        $this->patchJson('/api/v1/profile', ['email' => 'changed@example.com'])->assertUnprocessable();
        $this->patchJson('/api/v1/profile', ['email' => 'changed@example.com', 'current_password' => 'Original-Password-2026!'])->assertOk();
        $this->patchJson('/api/v1/profile', ['password' => 'Another-Password-2026!', 'password_confirmation' => 'Another-Password-2026!', 'current_password' => 'wrong'])->assertUnprocessable();
        $this->patchJson('/api/v1/profile', ['password' => 'Another-Password-2026!', 'password_confirmation' => 'Another-Password-2026!', 'current_password' => 'Original-Password-2026!'])->assertOk();
        $this->assertTrue(Hash::check('Another-Password-2026!', $user->fresh()->password));
        $this->assertStringNotContainsString('Password-2026', AuditEvent::where('action', 'profile_updated')->get()->toJson());
    }

    public function test_start_estimate_and_transition_are_atomic_and_preserve_existing_estimate(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $developer = User::factory()->create(['role' => 'developer']);
        $project = $this->project($manager, [$developer->id]);
        $issue = Issue::create(['project_id' => $project->id, 'reporter_id' => $manager->id, 'assignee_id' => $developer->id, 'title' => 'Task', 'description' => 'Requirements', 'status' => 'ready']);
        $url = '/api/v1/issues/'.$issue->id.'/transitions';
        $this->actingAs($developer)->postJson($url, ['status' => 'development', 'estimate' => 16])->assertOk()->assertJsonPath('data.remaining', 16);
        $this->assertEquals(16, $issue->fresh()->estimate);
        $this->postJson($url, ['status' => 'qa', 'estimate' => 100])->assertForbidden();
        $this->assertSame('development', $issue->fresh()->status);
        $this->assertEquals(16, $issue->fresh()->estimate);
        $this->assertSame(1, AuditEvent::where('issue_id', $issue->id)->where('action', 'status_changed')->count());
        $this->actingAs($manager)->patchJson('/api/v1/issues/'.$issue->id, ['tester_id' => null])->assertOk();
        $this->assertEquals(16, $issue->fresh()->remaining);
    }

    public function test_roadmap_warns_about_staffing_start_dates_and_overlaps_with_access_control(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21 12:00'));
        $manager = User::factory()->create(['role' => 'manager']);
        $developer = User::factory()->create(['role' => 'developer', 'weekly_capacity' => 32]);
        $project = $this->project($manager, [$developer->id]);
        $payload = ['project_id' => $project->id, 'title' => 'Annual work', 'deadline' => '2026-10-01', 'duration_days' => 60, 'required_people' => 3, 'status' => 'planned', 'developer_ids' => [$developer->id]];
        $id = $this->actingAs($manager)->postJson('/api/v1/roadmap', $payload)->assertCreated()->json('id');
        $this->postJson('/api/v1/roadmap', [...$payload, 'title' => 'Conflicting work'])->assertCreated();
        $row = $this->getJson('/api/v1/roadmap?year=2026')->assertOk()->assertJsonPath('total', 2)->json('data.0');
        $this->assertEquals(0.8, $row['assigned_fte']);
        $this->assertSame('2026-10-01', $row['deadline']);
        $this->assertContains('Плановый старт пропущен', $row['risks']);
        $this->assertContains('Не хватает 2.2 штатных единиц', $row['risks']);
        $this->assertContains('Пересечение исполнителей: 1 работ', $row['risks']);
        $this->actingAs($developer)->putJson('/api/v1/roadmap/'.$id, $payload)->assertForbidden();
        $this->getJson('/api/v1/roadmap?year=2026')->assertJsonPath('data.0.can_edit', false);
        $otherManager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($otherManager)->getJson('/api/v1/roadmap?year=2026')->assertJsonPath('total', 0);
        $otherProject = $this->project($otherManager, []);
        $this->putJson('/api/v1/roadmap/'.$id, [...$payload, 'project_id' => $otherProject->id, 'developer_ids' => []])->assertUnprocessable();
        $this->assertEquals($project->id, RoadmapItem::find($id)->project_id);
        $this->assertDatabaseHas('audit_events', ['action' => 'roadmap_saved']);
    }

    public function test_roadmap_includes_preparation_in_previous_year_and_paginates(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $project = $this->project($manager, []);
        RoadmapItem::factory()->count(26)->create(['project_id' => $project->id, 'deadline' => '2027-02-01', 'duration_days' => 65]);
        $this->actingAs($manager)->getJson('/api/v1/roadmap?year=2026')->assertJsonPath('total', 26)->assertJsonCount(25, 'data');
        $this->getJson('/api/v1/roadmap?year=2026&page=2')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/roadmap?year=2025')->assertJsonPath('total', 0);
        RoadmapItem::first()->update(['status' => 'done']);
        $this->getJson('/api/v1/roadmap?year=2026')->assertJsonPath('total', 25);
        $this->getJson('/api/v1/roadmap?year=2026&include_completed=1')->assertJsonPath('total', 26);
    }

    public function test_team_membership_management_does_not_grant_project_access(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $developer = User::factory()->create(['role' => 'developer']);
        $outsider = User::factory()->create(['role' => 'developer']);
        $project = $this->project($manager, [$developer->id]);
        $path = '/api/v1/teams/'.$project->team_id;
        $this->actingAs($manager)->putJson($path, ['name' => 'Renamed', 'member_ids' => [$outsider->id]])->assertForbidden();
        $this->putJson($path, ['name' => 'Renamed', 'member_ids' => []])->assertOk();
        $this->assertSame([$manager->id], $project->team->members()->pluck('users.id')->all());
        $this->assertTrue($project->members()->where('users.id', $developer->id)->exists());
        $this->actingAs($developer)->putJson($path, ['name' => 'Changed', 'member_ids' => []])->assertForbidden();
    }

    public function test_demo_seeder_adds_roadmap_without_resetting_existing_data(): void
    {
        $this->seed(DemoSeeder::class);
        $item = RoadmapItem::first();
        $item->update(['duration_days' => 42]);
        $count = RoadmapItem::count();
        $this->seed(DemoSeeder::class);
        $this->assertSame($count, RoadmapItem::count());
        $this->assertSame(42, $item->fresh()->duration_days);
    }
}
