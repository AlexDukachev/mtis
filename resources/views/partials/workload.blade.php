<section x-show="view === 'workload'">
    <div class="period-toolbar">
        <div class="period-tabs" role="group" aria-label="Период загрузки">
            <template
                x-for="p in [{id:'today',name:'Сегодня'},{id:'week',name:'Эта неделя'},{id:'next',name:'Следующая неделя'},{id:'fortnight',name:'2 недели'},{id:'month',name:'Месяц'}]"><button
                    :class="{ selected: period === p.id }" @click="period = p.id; refreshWorkload()"
                    x-text="p.name"></button></template>
        </div>
        <button class="button date-range" @click="period = 'custom'; from = workload.from; to = workload.to"
            :class="{ selected: period === 'custom' }"><span x-html="icon('CalendarDays')"></span><span
                x-text="date(workload.from) + ' — ' + date(workload.to, true)"></span><span
                x-html="icon('ChevronDown')"></span></button>
    </div>
    <div class="custom-period" x-show="period === 'custom'"><label>С<input type="date" x-model="from"
                @change="refreshWorkload()"></label><label>По<input type="date" x-model="to"
                @change="refreshWorkload()"></label></div>
    <div class="stats-grid">
        <div class="stat">
            <div class="stat-label">Загрузка команды<span class="stat-icon green"
                    x-html="icon('ChartNoAxesCombined')"></span></div>
            <div class="stat-value"><span x-text="totalPercent === null ? '—' : totalPercent"></span><small
                    x-show="totalPercent !== null">%</small></div>
            <div class="stat-foot"><span x-text="hours(workload.hours) + ' из ' + hours(workload.capacity)"></span><span
                    class="text-amber" x-show="workload.incomplete">Неполные данные</span></div>
        </div>
        <div class="stat">
            <div class="stat-label">В работе<span class="stat-icon blue" x-html="icon('ListTodo')"></span></div>
            <div class="stat-value" x-text="workload.rows.reduce((s,r) => s+r.wip, 0)"></div>
            <div class="stat-foot"><span x-text="workload.rows.length + ' разработчиков в команде'"></span></div>
        </div>
        <div class="stat">
            <div class="stat-label">С превышением емкости<span class="stat-icon red"
                    x-html="icon('TriangleAlert')"></span></div>
            <div class="stat-value" x-text="workload.overloaded || 0"></div>
            <div class="stat-foot text-red">Требуется перераспределение</div>
        </div>
        <div class="stat">
            <div class="stat-label">Есть свободная емкость<span class="stat-icon green" x-html="icon('Users')"></span>
            </div>
            <div class="stat-value" x-text="workload.available || 0"></div>
            <div class="stat-foot text-green">Можно назначить новые задачи</div>
        </div>
    </div>
    <div class="section-toolbar">
        <div class="filter-tabs"><button :class="{ selected: loadFilter === 'all' }"
                @click="loadFilter = 'all'">Все сотрудники <span x-text="workload.rows.length"></span></button><button
                :class="{ selected: loadFilter === 'overload' }" @click="loadFilter = 'overload'">Перегрузка <span
                    x-text="workload.overloaded"></span></button><button
                :class="{ selected: loadFilter === 'reserve' }" @click="loadFilter = 'reserve'">Есть резерв <span
                    x-text="workload.available"></span></button></div>
        <select aria-label="Команда" x-model="teamId" @change="refreshWorkload()">
            <option value="">Все команды</option><template x-for="t in teams">
                <option :value="t.id" x-text="t.name"></option>
            </template>
        </select>
    </div>
    <div class="data-table-wrap">
        <table class="data-table workload-table">
            <thead>
                <tr>
                    <th>Сотрудник</th>
                    <th>В работе</th>
                    <th>Осталось / емкость</th>
                    <th class="load-column">Загрузка</th>
                    <th>Просрочено</th>
                    <th>Блокировки</th>
                    <th></th>
                </tr>
            </thead>
            <template x-for="row in filteredRows" :key="row.user.id">
                <tbody>
                    <tr class="employee-row" @click="toggleEmployee(row)"
                        :class="{ 'row-selected': employee?.user.id === row.user.id }" tabindex="0"
                        @keydown.enter.prevent="toggleEmployee(row)" @keydown.space.prevent="toggleEmployee(row)">
                        <td>
                            <div class="person"><span class="avatar" :style="avatarStyle(row.user.id)"
                                    x-text="initials(row.user.name)"></span>
                                <div><strong x-text="row.user.name"></strong><small x-text="row.user.position"></small>
                                </div>
                            </div>
                        </td>
                        <td><span class="wip-count" :class="{ 'text-red': row.wip > row.user.wip_limit }"
                                x-text="row.wip"></span><span class="muted" x-text="' / ' + row.user.wip_limit"></span>
                        </td>
                        <td><strong class="numeric" x-text="hours(row.hours)"></strong><span class="muted"
                                x-text="' / ' + hours(row.capacity)"></span><small class="text-amber"
                                x-show="row.unestimated || row.unplanned"
                                x-text="row.unestimated ? row.unestimated + ' без оценки' : row.unplanned + ' без актуального плана'"></small>
                        </td>
                        <td>
                            <div class="load-meter"><span class="meter-track"><span :class="row.state"
                                        :style="'width:' + Math.min(row.percent ?? 0, 100) + '%'"></span></span><strong
                                    :class="'text-' + (row.state === 'overload' ? 'red' : row.state === 'reserve' ? 'green' :
                                        'default')"
                                    x-text="row.percent === null ? '—' : row.percent+'%'"></strong></div><small
                                :class="'state-' + row.state" x-text="stateName(row.state)"></small>
                        </td>
                        <td><span :class="row.overdue ? 'counter danger' : 'zero'" x-text="row.overdue || '—'"></span>
                        </td>
                        <td><span :class="row.blocked ? 'counter amber' : 'zero'" x-text="row.blocked || '—'"></span>
                        </td>
                        <td><button class="icon-button" :title="'Задачи: ' + row.user.name"
                                :aria-label="'Задачи: ' + row.user.name"
                                :aria-expanded="employee?.user.id === row.user.id"
                                :aria-controls="'employee-tasks-' + row.user.id" @keydown.enter.stop
                                @keydown.space.stop
                                x-html="icon(employee?.user.id === row.user.id ? 'ChevronDown' : 'ChevronRight')"></button>
                        </td>
                    </tr>
                    <template x-if="employee?.user.id === row.user.id">
                        <tr class="employee-detail-row">
                            <td colspan="7" class="employee-detail-cell">
                                <div class="employee-detail" :id="'employee-tasks-' + row.user.id" role="region"
                                    :aria-label="'Задачи сотрудника: ' + row.user.name">
                                    <div class="section-title">
                                        <div><span class="section-eyebrow">ЗАДАЧИ СОТРУДНИКА</span>
                                            <h2 x-text="row.user.name"></h2>
                                        </div>
                                        <button class="icon-button" title="Свернуть задачи"
                                            aria-label="Свернуть задачи"
                                            @click="const trigger = $el.closest('tbody').querySelector('.employee-row'); employee = null; $nextTick(() => trigger.focus())"
                                            x-html="icon('X')"></button>
                                    </div>
                                    <div class="alert amber" x-show="row.unestimated || row.unplanned"><span
                                            x-html="icon('TriangleAlert')"></span><span
                                            x-text="'Загрузка рассчитана не полностью: ' + row.unestimated + ' задач без оценки, ' + row.unplanned + ' без актуального периода планирования.'"></span>
                                    </div>
                                    <div class="employee-task-groups"><template
                                            x-for="group in [{title:'В работе',statuses:['development']},{title:'В очереди',statuses:['ready','new','review']},{title:'Возвраты и блокировки',statuses:['returned','blocked']}]">
                                            <div>
                                                <h3 x-text="group.title"></h3><template
                                                    x-for="task in row.issues.filter(i => group.statuses.includes(i.status))"
                                                    :key="task.id">
                                                    <button class="employee-task"
                                                        @click="openIssue(task.id)"><span><small
                                                                x-text="task.key"></small><strong
                                                                x-text="task.title"></strong></span><span
                                                            x-text="hours(task.remaining)"></span></button>
                                                </template>
                                                <p class="muted"
                                                    x-show="!row.issues.some(i => group.statuses.includes(i.status))">
                                                    Нет задач</p>
                                            </div>
                                        </template></div>
                                    <p class="muted" x-show="row.hidden_tasks"
                                        x-text="row.hidden_tasks + ' задач в других проектах учтены в загрузке; детали недоступны.'">
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </template>
            <tbody x-show="!filteredRows.length">
                <tr x-show="!filteredRows.length">
                    <td colspan="7" class="empty-cell">Нет сотрудников по выбранным условиям</td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="table-legend">
        <div><span><i class="legend-dot green"></i><span
                    x-text="'До ' + (workload.thresholds?.reserve || 70) + '% — есть резерв'"></span></span><span><i
                    class="legend-dot blue"></i>Плановая загрузка</span><span><i class="legend-dot red"></i>Превышение
                емкости</span></div><span x-text="filteredRows.length + ' сотрудников'"></span>
    </div>
    <div class="lower-grid">
        <section class="attention-section">
            <div class="section-title">
                <h2>Требуют внимания</h2><span class="subtle-badge"
                    x-text="dashboard.overdue + dashboard.blocked + dashboard.unestimated"></span>
            </div>
            <button class="attention-row" @click="go('issues',{overdue:'1'})"><span class="attention-icon red"
                    x-html="icon('Clock3')"></span>
                <div><strong>Просроченные задачи</strong><small>Установленный срок уже прошел</small></div><span
                    class="text-red" x-text="dashboard.overdue"></span><span x-html="icon('ChevronRight')"></span>
            </button>
            <button class="attention-row" @click="go('issues',{blocked:'1'})"><span class="attention-icon amber"
                    x-html="icon('LockKeyhole')"></span>
                <div><strong>Заблокированные задачи</strong><small>Ожидают решения зависимостей</small></div><span
                    x-text="dashboard.blocked"></span><span x-html="icon('ChevronRight')"></span>
            </button>
            <button class="attention-row" @click="go('issues',{unestimated:'1'})"><span class="attention-icon blue"
                    x-html="icon('CircleAlert')"></span>
                <div><strong>Задачи без оценки</strong><small>Не учтены в полной загрузке</small></div><span
                    x-text="dashboard.unestimated"></span><span x-html="icon('ChevronRight')"></span>
            </button>
        </section>
        <section class="queue-section">
            <div class="section-title">
                <h2>Очередь тестирования</h2><button class="text-button" @click="go('qa')">Все задачи <span
                        x-html="icon('ArrowUpRight')"></span></button>
            </div>
            <div class="qa-numbers">
                <div><strong x-text="dashboard.statuses.qa || 0"></strong><span>Ожидают проверки</span></div>
                <div><strong x-text="dashboard.statuses.testing || 0"></strong><span>На тестировании</span></div>
                <div><strong x-text="dashboard.statuses.returned || 0"></strong><span>Возвращены</span></div>
            </div>
            <div class="queue-bar"><span class="qa-wait" :style="'flex:' + (dashboard.statuses.qa || 1)"></span><span
                    class="qa-test" :style="'flex:' + (dashboard.statuses.testing || 1)"></span><span
                    class="qa-return" :style="'flex:' + (dashboard.statuses.returned || 1)"></span></div>
            <p class="queue-caption"><span x-html="icon('Clock3')"></span>Среднее ожидание <strong
                    x-text="dashboard.qa_wait_days + ' раб. дня'"></strong></p>
        </section>
    </div>
</section>
