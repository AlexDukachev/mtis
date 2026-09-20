<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\RoadmapItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoadmapItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo data is only available locally.');
        }
        $year = now()->year + 1;
        foreach ([
            ['ENBEK', 'Единый кабинет работодателя', "$year-04-30", 65, 3, ['ivanov', 'sidorov']],
            ['AIS', 'Переход на обновленный реестр организаций', "$year-06-30", 60, 3, ['ivanov', 'smirnova', 'akhmetov']],
            ['SKILLS', 'Новая система оценки компетенций', "$year-09-30", 45, 2, ['volkova', 'petrov']],
            ['MANSAP', 'Запуск рекомендаций вакансий', today()->addWeeks(8)->toDateString(), 65, 3, ['sidorov']],
        ] as [$key, $title, $deadline, $days, $people, $names]) {
            $project = Project::where('key', $key)->first();
            if (! $project || RoadmapItem::where('project_id', $project->id)->where('title', $title)->exists()) {
                continue;
            }
            $item = RoadmapItem::create(['project_id' => $project->id, 'title' => $title, 'deadline' => $deadline, 'duration_days' => $days, 'required_people' => $people,
                'description' => 'Согласовать требования, реализовать изменения и провести приемочные испытания.', 'status' => 'planned']);
            $item->developers()->sync(User::whereIn('username', $names)->whereHas('projects', fn ($q) => $q->where('projects.id', $project->id))->pluck('id'));
        }
    }
}
