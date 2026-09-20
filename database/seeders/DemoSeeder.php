<?php

namespace Database\Seeders;

use App\Models\AuditEvent;
use App\Models\InboxNotification;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo data is only available locally.');
        }
        if (User::where('email', 'admin@mtis.test')->exists()) {
            $this->call(RoadmapItemSeeder::class);

            return;
        }
        DB::transaction(function () {
            $password = Hash::make('Mtis-Demo-2026!');
            $people = [
                ['Александр Ким', 'admin', 'admin', 'Руководитель разработки'],
                ['Мария Соколова', 'manager', 'manager', 'Руководитель команды'],
                ['Иван Иванов', 'ivanov', 'developer', 'Backend-разработчик'],
                ['Дмитрий Петров', 'petrov', 'developer', 'Fullstack-разработчик'],
                ['Алексей Сидоров', 'sidorov', 'developer', 'Frontend-разработчик'],
                ['Анна Смирнова', 'smirnova', 'developer', 'Backend-разработчик'],
                ['Тимур Ахметов', 'akhmetov', 'developer', 'Backend-разработчик'],
                ['Елена Волкова', 'volkova', 'developer', 'Frontend-разработчик'],
                ['Дарья Козлова', 'kozlova', 'tester', 'QA-инженер'],
                ['Андрей Морозов', 'morozov', 'tester', 'QA-инженер'],
                ['Ольга Попова', 'popova', 'reporter', 'Бизнес-аналитик'],
                ['Сергей Орлов', 'orlov', 'observer', 'Наблюдатель'],
            ];
            $users = [];
            foreach ($people as [$name, $username, $role, $position]) {
                $users[$username] = User::create(['name' => $name, 'username' => $username, 'email' => $username.'@mtis.test', 'password' => $password,
                    'role' => $role, 'position' => $position, 'department' => 'Цифровые сервисы', 'active' => true,
                    'weekly_capacity' => $username === 'smirnova' ? 32 : 40, 'work_days' => [1, 2, 3, 4, 5], 'wip_limit' => 3]);
            }
            $team = Team::create(['name' => 'Цифровые сервисы', 'leader_id' => $users['manager']->id]);
            $team->members()->sync(collect($users)->pluck('id'));
            $projects = [];
            foreach ([['Enbek', 'ENBEK', '#16866a'], ['АИС РТ', 'AIS', '#537ac1'], ['Skills', 'SKILLS', '#ae6b38'], ['MANSAP', 'MANSAP', '#8a67ae'], ['Ауыл Аманаты', 'AUYL', '#cf6e7a']] as [$name, $key, $color]) {
                $project = Project::create(['name' => $name, 'key' => $key, 'color' => $color, 'team_id' => $team->id, 'owner_id' => $users['manager']->id, 'description' => 'Развитие и сопровождение проекта '.$name, 'status' => 'active']);
                $project->members()->sync(collect($users)->pluck('id'));
                $projects[$key] = $project;
            }
            $tasks = [
                ['ENBEK', 'Перенос предприятия между кабинетами работодателей', 'ivanov', 'development', 12, 'high', 3],
                ['ENBEK', 'Проверка доступа к API авторизации', 'ivanov', 'development', 6, 'normal', 2],
                ['AIS', 'Обновить форму регистрации заявителя', 'ivanov', 'ready', 16, 'normal', 5],
                ['ENBEK', 'Исправить проверку БИН при регистрации', 'petrov', 'returned', 8, 'critical', -2],
                ['AIS', 'Оптимизация поиска по реестру организаций', 'petrov', 'development', 20, 'high', -1],
                ['SKILLS', 'Синхронизация каталога компетенций', 'petrov', 'blocked', 12, 'high', 4],
                ['ENBEK', 'Обновить сервис отправки уведомлений', 'petrov', 'ready', 12, 'normal', 5],
                ['MANSAP', 'Новая форма профиля соискателя', 'sidorov', 'development', 12, 'normal', 3],
                ['ENBEK', 'Выгрузка отчета по обращениям работодателей', 'smirnova', 'development', 16, 'normal', 4],
                ['AUYL', 'Проверка статусов заявки на финансирование', 'smirnova', 'ready', 8, 'normal', 5],
                ['AIS', 'Миграция справочника специальностей', 'akhmetov', 'development', 18, 'high', 2],
                ['ENBEK', 'Исследовать причины задержки ответа API', 'akhmetov', 'ready', null, 'high', 4],
                ['SKILLS', 'Адаптация личного кабинета для планшетов', 'volkova', 'development', 8, 'normal', 4],
                ['MANSAP', 'Отображение статуса отклика на вакансию', 'volkova', 'ready', 12, 'low', 5],
                ['ENBEK', 'Проверить экспорт списка работодателей', 'ivanov', 'qa', 0, 'normal', 2],
                ['AIS', 'Исправление фильтра по районам', 'smirnova', 'testing', 0, 'high', 1],
                ['SKILLS', 'Сохранение результатов тестирования навыков', 'petrov', 'qa', 0, 'critical', 1],
                ['MANSAP', 'Обновление карточки вакансии', 'volkova', 'acceptance', 0, 'normal', 2],
                ['AUYL', 'Добавить поиск заявок по ИИН', null, 'new', null, 'high', 7],
                ['ENBEK', 'Уточнить права регионального администратора', null, 'review', null, 'normal', 6],
                ['ENBEK', 'Документировать коды ошибок API', 'sidorov', 'ready', 16, 'normal', 10],
                ['AIS', 'Новая форма ежемесячной статистики', 'ivanov', 'ready', 24, 'normal', 11],
                ['SKILLS', 'Исправить отображение сертификата', 'volkova', 'closed', 0, 'normal', -3],
                ['ENBEK', 'Валидация номера телефона', 'smirnova', 'closed', 0, 'normal', -5],
            ];
            foreach ($tasks as $index => [$key, $title, $assignee, $status, $remaining, $priority, $due]) {
                $start = today()->startOfWeek();
                if ($index >= 20 && $index <= 21) {
                    $start = $start->addWeek();
                }
                $issue = Issue::create(['project_id' => $projects[$key]->id, 'reporter_id' => $users['popova']->id,
                    'assignee_id' => $assignee ? $users[$assignee]->id : null, 'tester_id' => $users[$index % 2 === 0 ? 'kozlova' : 'morozov']->id,
                    'title' => $title, 'description' => 'Необходимо реализовать изменение в проекте '.$projects[$key]->name.".\n\nКритерии приемки:\n1. Данные сохраняются корректно.\n2. Проверены права доступа пользователей.\n3. Существующие сценарии работают без изменений.",
                    'status' => $status, 'priority' => $priority, 'type' => $status === 'returned' ? 'bug' : 'task',
                    'estimate' => $remaining === null ? null : max(8, $remaining + 4), 'remaining' => $remaining,
                    'due_date' => today()->addDays($due), 'planned_start' => $start, 'planned_end' => $start->copy()->addDays(4),
                    'return_count' => $status === 'returned' ? 1 : 0, 'block_reason' => $status === 'blocked' ? 'Ожидается доступ к API внешнего каталога.' : null,
                    'qa_queued_at' => in_array($status, ['qa', 'testing']) ? now()->subWeekdays(2) : null,
                    'qa_started_at' => $status === 'testing' ? now()->subHours(2) : null,
                    'closed_at' => $status === 'closed' ? now()->subDays(2) : null,
                    'component' => $index % 2 === 0 ? 'Backend' : 'Frontend', 'tags' => [$index % 2 === 0 ? 'api' : 'interface'],
                ]);
                $issue->update(['key' => $key.'-'.$issue->id]);
                AuditEvent::create(['user_id' => $users['popova']->id, 'issue_id' => $issue->id, 'action' => 'created', 'created_at' => now()->subDays(5)]);
                AuditEvent::create(['user_id' => $assignee ? $users[$assignee]->id : $users['manager']->id, 'issue_id' => $issue->id, 'action' => 'status_changed',
                    'changes' => ['status' => ['from' => 'ready', 'to' => $status]], 'created_at' => now()->subMinutes(($index + 1) * 14)]);
                if ($assignee && $remaining !== null) {
                    $issue->worklogs()->create(['user_id' => $users[$assignee]->id, 'hours' => 2, 'date' => today()->subDays(2), 'comment' => 'Анализ требований и подготовка решения.']);
                }
                if ($status === 'returned') {
                    $issue->comments()->create(['user_id' => $users['kozlova']->id, 'body' => 'При вводе БИН с ведущим нулем система отклоняет корректное значение. Необходимо сохранить строковый формат.',
                        'test_result' => ['expected' => 'БИН проходит проверку', 'actual' => 'Ошибка валидации', 'steps' => 'Открыть регистрацию, ввести БИН с ведущим нулем, сохранить', 'environment' => 'Тестовый стенд, Chrome']]);
                }
                if ($index < 5) {
                    InboxNotification::create(['user_id' => $users['admin']->id, 'issue_id' => $issue->id, 'type' => 'status_changed', 'title' => $issue->key.': изменен статус']);
                }
            }
        });
        $this->call(RoadmapItemSeeder::class);
    }
}
