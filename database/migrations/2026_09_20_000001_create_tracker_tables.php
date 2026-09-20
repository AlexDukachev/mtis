<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique();
            $table->string('role')->default('observer');
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->boolean('active')->default(true);
            $table->decimal('weekly_capacity', 6, 2)->default(40);
            $table->json('work_days')->nullable();
            $table->unsignedInteger('wip_limit')->default(3);
            $table->json('notification_preferences')->nullable();
        });
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('leader_id')->constrained('users');
            $table->timestamps();
        });
        Schema::create('team_user', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['team_id', 'user_id']);
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->text('description')->nullable();
            $table->string('color')->default('#17866b');
            $table->string('status')->default('active');
            $table->foreignId('team_id')->constrained();
            $table->foreignId('owner_id')->constrained('users');
            $table->json('workflow')->nullable();
            $table->timestamps();
        });
        Schema::create('project_user', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'user_id']);
        });
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->string('key')->nullable()->unique();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('reporter_id')->constrained('users');
            $table->foreignId('assignee_id')->nullable()->constrained('users');
            $table->foreignId('tester_id')->nullable()->constrained('users');
            $table->foreignId('parent_id')->nullable()->constrained('issues');
            $table->string('title');
            $table->text('description');
            $table->string('type')->default('task');
            $table->string('status')->default('new');
            $table->string('priority')->default('normal');
            $table->date('due_date')->nullable();
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->decimal('estimate', 8, 2)->nullable();
            $table->decimal('remaining', 8, 2)->nullable();
            $table->unsignedInteger('return_count')->default(0);
            $table->text('block_reason')->nullable();
            $table->text('solution')->nullable();
            $table->string('component')->nullable();
            $table->string('version')->nullable();
            $table->string('environment')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('qa_queued_at')->nullable();
            $table->timestamp('qa_started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
            $table->index(['assignee_id', 'status']);
            $table->index('due_date');
        });
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('parent_id')->nullable()->constrained('comments');
            $table->text('body');
            $table->json('test_result')->nullable();
            $table->timestamps();
        });
        Schema::create('worklogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->date('date');
            $table->decimal('hours', 6, 2);
            $table->text('comment')->nullable();
            $table->timestamps();
        });
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('name');
            $table->string('path');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->foreignId('issue_id')->nullable()->constrained();
            $table->string('action');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('inbox_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('issue_id')->nullable()->constrained();
            $table->string('type');
            $table->string('title');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });
        Schema::create('calendar_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->date('date');
            $table->decimal('hours', 5, 2)->default(0);
            $table->string('reason');
            $table->timestamps();
            $table->index('date');
        });
        Schema::create('saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('name');
            $table->json('filters');
            $table->timestamps();
        });
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
        });
    }

    public function down(): void
    {
        foreach (['settings', 'saved_filters', 'calendar_exceptions', 'inbox_notifications', 'audit_events', 'attachments', 'worklogs', 'comments', 'issues', 'project_user', 'projects', 'team_user', 'teams'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['username', 'role', 'department', 'position', 'active', 'weekly_capacity', 'work_days', 'wip_limit', 'notification_preferences']));
    }
};
