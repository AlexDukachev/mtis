<?php

return [
    'statuses' => [
        'new' => 'Новая', 'review' => 'На рассмотрении', 'clarification' => 'Требуется уточнение',
        'ready' => 'Готова к разработке', 'development' => 'В разработке', 'qa' => 'Ожидает тестирования',
        'testing' => 'На тестировании', 'returned' => 'Возвращена разработчику', 'acceptance' => 'Готова к приемке',
        'blocked' => 'Заблокирована', 'deferred' => 'Отложена', 'closed' => 'Закрыта', 'cancelled' => 'Отменена',
    ],
    'types' => ['task' => 'Задача', 'bug' => 'Ошибка', 'improvement' => 'Доработка', 'research' => 'Исследование', 'debt' => 'Технический долг', 'infrastructure' => 'Инфраструктура'],
    'priorities' => ['low' => 'Низкий', 'normal' => 'Обычный', 'high' => 'Высокий', 'critical' => 'Критический', 'blocking' => 'Блокирующий'],
    'roles' => ['admin' => 'Администратор', 'manager' => 'Руководитель', 'reporter' => 'Постановщик', 'developer' => 'Разработчик', 'tester' => 'Тестировщик', 'observer' => 'Наблюдатель'],
    'permissions' => [
        'manager' => ['create', 'comment', 'assign', 'estimate', 'develop', 'test', 'accept', 'reopen', 'reports'],
        'reporter' => ['create', 'comment', 'accept', 'reopen'],
        'developer' => ['create', 'comment', 'estimate', 'develop', 'worklog'],
        'tester' => ['create', 'comment', 'test'], 'observer' => [],
    ],
    'workflow' => [
        'new' => ['review', 'clarification', 'ready', 'cancelled'],
        'review' => ['clarification', 'ready', 'deferred', 'cancelled'],
        'clarification' => ['review', 'ready', 'cancelled'],
        'ready' => ['development', 'blocked', 'deferred', 'cancelled'],
        'development' => ['qa', 'blocked', 'clarification'], 'qa' => ['testing'],
        'testing' => ['returned', 'acceptance'], 'returned' => ['development', 'blocked'],
        'acceptance' => ['closed', 'returned'], 'blocked' => ['ready', 'development', 'deferred', 'cancelled'],
        'deferred' => ['review', 'ready', 'cancelled'], 'closed' => ['review'], 'cancelled' => ['review'],
    ],
    'thresholds' => ['reserve' => 70, 'overload' => 100],
    'max_file_kb' => 20480, 'max_issue_bytes' => 104857600,
];
