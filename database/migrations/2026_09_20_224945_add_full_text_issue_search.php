<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('issues', fn (Blueprint $table) => $table->fullText(['key', 'title', 'description']));
            Schema::table('comments', fn (Blueprint $table) => $table->fullText('body'));
            Schema::table('users', fn (Blueprint $table) => $table->fullText(['name', 'username']));

            return;
        }
        DB::statement("CREATE VIRTUAL TABLE issue_search USING fts5(key, title, description, comments, people, tokenize='unicode61')");
        $people = "(SELECT group_concat(name || ' ' || coalesce(username, ''), ' ') FROM users WHERE id IN (issues.assignee_id, issues.reporter_id, issues.tester_id))";
        DB::statement("INSERT INTO issue_search(rowid, key, title, description, comments, people) SELECT id, key, title, description, (SELECT group_concat(body, ' ') FROM comments WHERE issue_id = issues.id), $people FROM issues");
        DB::unprepared("CREATE TRIGGER issue_search_insert AFTER INSERT ON issues BEGIN
            INSERT INTO issue_search(rowid, key, title, description, people) SELECT id, key, title, description, $people FROM issues WHERE id = new.id;
        END");
        DB::unprepared("CREATE TRIGGER issue_search_update AFTER UPDATE ON issues BEGIN
            UPDATE issue_search SET key = new.key, title = new.title, description = new.description,
            people = (SELECT $people FROM issues WHERE id = new.id) WHERE rowid = new.id;
        END");
        DB::unprepared('CREATE TRIGGER issue_search_delete AFTER DELETE ON issues BEGIN DELETE FROM issue_search WHERE rowid = old.id; END');
        foreach (['INSERT' => 'new', 'UPDATE' => 'new', 'DELETE' => 'old'] as $operation => $reference) {
            $name = strtolower($operation);
            DB::unprepared("CREATE TRIGGER comment_search_$name AFTER $operation ON comments BEGIN
                UPDATE issue_search SET comments = (SELECT group_concat(body, ' ') FROM comments WHERE issue_id = $reference.issue_id) WHERE rowid = $reference.issue_id;
            END");
        }
        DB::unprepared("CREATE TRIGGER user_search_update AFTER UPDATE OF name, username ON users BEGIN
            UPDATE issue_search SET people = (SELECT $people FROM issues WHERE issues.id = issue_search.rowid)
            WHERE rowid IN (SELECT id FROM issues WHERE assignee_id = new.id OR reporter_id = new.id OR tester_id = new.id);
        END");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('issues', fn (Blueprint $table) => $table->dropFullText(['key', 'title', 'description']));
            Schema::table('comments', fn (Blueprint $table) => $table->dropFullText(['body']));
            Schema::table('users', fn (Blueprint $table) => $table->dropFullText(['name', 'username']));

            return;
        }
        foreach (['issue_search_insert', 'issue_search_update', 'issue_search_delete', 'comment_search_insert', 'comment_search_update', 'comment_search_delete', 'user_search_update'] as $trigger) {
            DB::statement("DROP TRIGGER IF EXISTS $trigger");
        }
        DB::statement('DROP TABLE IF EXISTS issue_search');
    }
};
