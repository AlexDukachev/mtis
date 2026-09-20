<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme')->default('system');
        });
        Schema::create('roadmap_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('deadline')->index();
            $table->unsignedInteger('duration_days');
            $table->unsignedInteger('required_people');
            $table->string('status')->default('planned');
            $table->timestamps();
        });
        Schema::create('roadmap_item_user', function (Blueprint $table) {
            $table->foreignId('roadmap_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->primary(['roadmap_item_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roadmap_item_user');
        Schema::dropIfExists('roadmap_items');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('theme'));
    }
};
