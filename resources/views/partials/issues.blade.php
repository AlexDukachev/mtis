<section x-show="['issues','mine','kanban','qa'].includes(view)">
    <div class="issue-toolbar">
        <div class="search-field"><span x-html="icon('Search')"></span><input placeholder="Номер, название или описание"
                aria-label="Поиск задач" x-model="filters.q" @input.debounce.350ms="filterIssues()"></div><select
            aria-label="Фильтр проекта" x-model="filters.project_id" @change="filterIssues()">
            <option value="">Все проекты</option><template x-for="p in projects">
                <option :value="p.id" x-text="p.name"></option>
            </template>
        </select><select aria-label="Фильтр статуса" x-model="filters.status" @change="filterIssues()">
            <option value="">Все статусы</option><template x-for="[key, name] in Object.entries(statuses)">
                <option :value="key" x-text="name"></option>
            </template>
        </select><button class="button" @click="showFilters = !showFilters"><span
                x-html="icon('Filter')"></span>Фильтры<span class="subtle-badge" x-show="activeFilters"
                x-text="activeFilters"></span></button><button class="icon-button" title="Настроить колонки"
            aria-label="Настроить колонки" @click="showColumns = !showColumns"
            x-html="icon('SlidersHorizontal')"></button>
    </div>
    <div class="filter-panel" x-show="showFilters">
        <label>Исполнитель<select x-model="filters.assignee_id" @change="filterIssues()">
                <option value="">Все</option><template x-for="u in users.filter(u => u.role === 'developer')">
                    <option :value="u.id" x-text="u.name"></option>
                </template>
            </select></label>
        <label>Постановщик<select x-model="filters.reporter_id" @change="filterIssues()">
                <option value="">Все</option><template x-for="u in users">
                    <option :value="u.id" x-text="u.name"></option>
                </template>
            </select></label>
        <label>Тестировщик<select x-model="filters.tester_id" @change="filterIssues()">
                <option value="">Все</option><template x-for="u in users.filter(u => u.role === 'tester')">
                    <option :value="u.id" x-text="u.name"></option>
                </template>
            </select></label>
        <label>Приоритет<select x-model="filters.priority" @change="filterIssues()">
                <option value="">Все</option><template x-for="[k,v] in Object.entries(priorities)">
                    <option :value="k" x-text="v"></option>
                </template>
            </select></label>
        <label>Тип<select x-model="filters.type" @change="filterIssues()">
                <option value="">Все</option><template x-for="[k,v] in Object.entries(types)">
                    <option :value="k" x-text="v"></option>
                </template>
            </select></label>
        <label>Срок до<input type="date" x-model="filters.due_before" @change="filterIssues()"></label>
        <label class="checkbox"><input type="checkbox" x-model="filters.overdue" true-value="1" false-value=""
                @change="filterIssues()">Просроченные</label><label class="checkbox"><input type="checkbox"
                x-model="filters.unestimated" true-value="1" false-value="" @change="filterIssues()">Без
            оценки</label><label class="checkbox"><input type="checkbox" x-model="filters.unassigned" true-value="1"
                false-value="" @change="filterIssues()">Без исполнителя</label>
        <button class="text-button" @click="filterName = ''; openModal('filter')"><span
                x-html="icon('Save')"></span>Сохранить фильтр</button><button class="text-button"
            @click="filters = {}; filterIssues()">Сбросить</button>
    </div>
    <div class="column-panel" x-show="showColumns"><template
            x-for="[key,name] in Object.entries({project:'Проект',status:'Статус',assignee:'Исполнитель',priority:'Приоритет',due:'Срок',estimate:'Осталось'})"><label
                class="checkbox"><input type="checkbox" x-model="columns[key]"><span
                    x-text="name"></span></label></template></div>
    <div class="saved-filters" x-show="savedFilters.length"><template x-for="f in savedFilters"><span><button
                    @click="filters = {...f.filters}; filterIssues()" x-text="f.name"></button><button
                    class="icon-button" title="Удалить фильтр" aria-label="Удалить фильтр" @click="deleteFilter(f.id)"
                    x-html="icon('X')"></button></span></template></div>
    <div class="section-toolbar">
        <div class="filter-tabs"><button :class="{ selected: !filters.status }"
                @click="filters.status = ''; filterIssues()">Все задачи <span
                    x-text="pagination.total || 0"></span></button><button
                :class="{ selected: filters.status === 'development' }"
                @click="filters.status = 'development'; filterIssues()">В работе</button><button
                :class="{ selected: filters.status === 'returned' }"
                @click="filters.status = 'returned'; filterIssues()">Возвраты QA</button></div>
        <div class="segmented"><button title="Список" aria-label="Список"
                :class="{ selected: view !== 'kanban' }"
                @click="view = view === 'kanban' ? 'issues' : view; filterIssues()"
                x-html="icon('ListTodo')"></button><button title="Доска задач" aria-label="Доска задач"
                :class="{ selected: view === 'kanban' }" @click="view = 'kanban'; filterIssues()"
                x-html="icon('Columns3')"></button></div>
    </div>
    <div class="pagination pagination-top"><span x-text="'Всего задач: '+(pagination.total || 0)"></span><label
            class="inline-label">На странице<select aria-label="Задач на странице" x-model.number="perPage"
                @change="filterIssues()"><template x-for="n in [10,25,50,100]">
                    <option :value="n" x-text="n"></option>
                </template></select></label></div>
    <div class="data-table-wrap" x-show="view !== 'kanban'">
        <table class="data-table issue-table">
            <thead>
                <tr>
                    <th>Задача</th>
                    <th x-show="columns.project">Проект</th>
                    <th x-show="columns.status">Статус</th>
                    <th x-show="columns.assignee">Исполнитель</th>
                    <th x-show="columns.priority">Приоритет</th>
                    <th x-show="columns.due">Срок</th>
                    <th x-show="columns.estimate">Осталось</th>
                </tr>
            </thead>
            <tbody><template x-for="issue in issues" :key="issue.id">
                    <tr @click="openIssue(issue.id)" tabindex="0" @keydown.enter="openIssue(issue.id)">
                        <td class="issue-title-cell"><small x-text="issue.key"></small><strong
                                x-text="issue.title"></strong><span class="task-subline"
                                x-show="view === 'qa' && issue.qa_queued_at"
                                x-text="issue.qa_queued_at ? 'Передана ' + time(issue.qa_queued_at) : ''"></span></td>
                        <td x-show="columns.project"><span class="project-label"><i
                                    :style="'background:' + issue.project.color"></i><span
                                    x-text="issue.project.name"></span></span></td>
                        <td x-show="columns.status"><span class="status-badge"
                                :class="'status-' + issue.status"><i></i><span
                                    x-text="statuses[issue.status]"></span></span></td>
                        <td x-show="columns.assignee">
                            <button class="person compact"
                                :title="issue.can_assign ? 'Назначить исполнителя' : 'Открыть профиль'"
                                @click.stop="issue.can_assign && !['closed','cancelled'].includes(issue.status) ? beginAssign(issue) : openProfile(issue.assignee)"
                                @keydown.enter.stop><span class="avatar small" :style="avatarStyle(issue.assignee_id)"
                                    x-text="initials(issue.assignee?.name) || '—'"></span><span
                                    x-text="issue.assignee?.name || 'Назначить'"></span></button>
                        </td>
                        <td x-show="columns.priority"><span class="priority"
                                :class="'priority-' + issue.priority"><i></i><span
                                    x-text="priorities[issue.priority]"></span></span></td>
                        <td x-show="columns.due" :class="{ 'text-red': issue.overdue }"
                            x-text="date(issue.due_date)"></td>
                        <td x-show="columns.estimate" :class="{ 'text-amber': issue.remaining === null }"
                            x-text="hours(issue.remaining)"></td>
                    </tr>
                </template></tbody>
        </table>
    </div>
    <div class="kanban" x-show="view === 'kanban'"><template x-for="[status,name] in Object.entries(statuses)"
            :key="status">
            <section class="kanban-column" @dragover.prevent @drop.prevent="dropIssue(status)">
                <div class="kanban-heading"><span class="status-badge" :class="'status-' + status"><i></i><span
                            x-text="name"></span></span><span
                        x-text="issues.filter(i => i.status === status).length"></span></div><template
                    x-for="issue in issues.filter(i => i.status === status)" :key="issue.id"><button
                        class="kanban-card" draggable="true" @dragstart="dragged = issue.id"
                        @click="openIssue(issue.id)"><small x-text="issue.key"></small><strong
                            x-text="issue.title"></strong><span class="priority"
                            :class="'priority-' + issue.priority"><i></i><span
                                x-text="priorities[issue.priority]"></span></span>
                        <div class="kanban-card-foot"><span :class="{ 'text-red': issue.overdue }"
                                x-text="date(issue.due_date)"></span><span class="avatar small"
                                :style="avatarStyle(issue.assignee_id)"
                                x-text="initials(issue.assignee?.name) || '—'"></span></div>
                    </button></template>
            </section>
        </template></div>
    <div class="empty-state" x-show="!issues.length && !busy"><span x-html="icon('ListTodo')"></span>
        <h3>Задач пока нет</h3>
        <p>По выбранным условиям ничего не найдено</p><button class="button"
            @click="filters = {}; filterIssues()">Сбросить фильтры</button>
    </div>
    <div class="pagination"><span
            x-text="pagination.total ? (pagination.from + '–' + pagination.to + ' из ' + pagination.total + ' задач') : '0 задач'"></span>
        <div><button class="icon-button" title="Предыдущая страница" aria-label="Предыдущая страница"
                :disabled="page <= 1 || busy" @click="page--; run(() => loadIssues())"
                x-html="icon('ChevronLeft')"></button><span
                x-text="page + ' / ' + (pagination.last_page || 1)"></span><button class="icon-button"
                title="Следующая страница" aria-label="Следующая страница"
                :disabled="page >= pagination.last_page || busy" @click="page++; run(() => loadIssues())"
                x-html="icon('ChevronRight')"></button></div>
    </div>
</section>
