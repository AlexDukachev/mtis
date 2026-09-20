<div class="modal-overlay" x-show="modal" x-cloak @click.self="closeModal()">
    <section class="modal"
        :class="{ 'issue-modal': modal === 'issue', 'wide-modal': ['create', 'user', 'project'].includes(modal) }"
        role="dialog" aria-modal="true" :aria-label="modal === 'issue' ? selected?.title : 'Редактирование'"
        x-trap.inert.noscroll="!!modal">
        <div class="modal-top"><span class="section-eyebrow"
                x-text="modal === 'issue' ? selected?.key : ({create:issueEditing?'РЕДАКТИРОВАНИЕ ЗАДАЧИ':'НОВАЯ ЗАДАЧА',transition:'СМЕНА СТАТУСА',estimate:'ОЦЕНКА И РЕШЕНИЕ',worklog:'ТРУДОЗАТРАТЫ',user:'ПОЛЬЗОВАТЕЛЬ',project:'ПРОЕКТ',team:'КОМАНДА',calendar:'РАБОЧИЙ КАЛЕНДАРЬ',filter:'СОХРАНЕННЫЙ ФИЛЬТР',preferences:'УВЕДОМЛЕНИЯ'})[modal]"></span><button
                class="icon-button" title="Закрыть" aria-label="Закрыть окно" :disabled="busy"
                @click="closeModal()" x-html="icon('X')"></button></div>
        <div class="alert danger" x-show="error" role="alert"><span x-html="icon('CircleAlert')"></span><span
                x-text="error"></span></div>
        <template x-if="modal === 'issue' && selected">
            <div>
                <div class="issue-modal-heading"><span class="project-label"><i
                            :style="'background:' + selected.project.color"></i><span
                            x-text="selected.project.name"></span></span>
                    <h2 x-text="selected.title"></h2>
                    <div class="issue-meta-line"><span class="status-badge"
                            :class="'status-' + selected.status"><i></i><span
                                x-text="statuses[selected.status]"></span></span><span class="priority"
                            :class="'priority-' + selected.priority"><i></i><span
                                x-text="priorities[selected.priority]"></span></span><span class="muted"
                            x-text="types[selected.type]"></span><span class="subtle-badge"
                            x-show="selected.return_count" x-text="'Возвраты: '+selected.return_count"></span></div>
                </div>
                <div class="issue-actions"><template x-for="target in primaryTransitions"><button class="button"
                            :class="{ 'primary': ['development', 'testing', 'qa', 'acceptance', 'closed'].includes(target) }"
                            @click="beginTransition(target)"><span
                                x-html="icon(target === 'returned' ? 'RotateCcw' : target === 'blocked' ? 'LockKeyhole' : 'ArrowRight')"></span><span
                                x-text="actionName(target)"></span></button></template><button class="button"
                        x-show="selected.can_assign && !['closed','cancelled'].includes(selected.status)"
                        @click="beginAssign()"><span x-html="icon('UserPlus')"></span>Назначить</button><button
                        class="button" x-show="selected.can_edit && !['closed','cancelled'].includes(selected.status)"
                        @click="editIssue()"><span x-html="icon('Settings')"></span>Изменить</button><select
                        class="extra-actions" aria-label="Другие действия" x-show="extraTransitions.length"
                        @change="if($event.target.value) beginTransition($event.target.value); $event.target.value='' ">
                        <option value="">Другие действия</option><template x-for="target in extraTransitions">
                            <option :value="target" x-text="actionName(target)"></option>
                        </template>
                    </select></div>
                <div class="issue-detail-grid">
                    <div class="issue-main">
                        <div class="alert amber" x-show="selected.block_reason"><span
                                x-html="icon('LockKeyhole')"></span><span x-text="selected.block_reason"></span></div>
                        <h3>Описание</h3>
                        <p class="description-text" x-text="selected.description"></p>
                        <template x-if="selected.solution">
                            <div>
                                <h3>Техническое решение</h3>
                                <p class="description-text" x-text="selected.solution"></p>
                            </div>
                        </template>
                        <div class="section-title">
                            <h3>Подзадачи <span class="muted"
                                    x-text="selected.children?.length ? selected.children.filter(i => ['closed','cancelled'].includes(i.status)).length + ' / ' + selected.children.length : ''"></span>
                            </h3><button class="icon-button" title="Добавить подзадачу" aria-label="Добавить подзадачу"
                                x-show="permissions.includes('create')" @click="newIssue(selected)"
                                x-html="icon('Plus')"></button>
                        </div>
                        <template x-for="child in selected.children"><button class="subtask"
                                @click="openIssue(child.id)"><span
                                    x-html="icon(child.status === 'closed' ? 'CircleCheck' : 'Circle')"></span><small
                                    x-text="child.key"></small><span x-text="child.title"></span></button></template>
                        <div class="detail-tabs"><button :class="{ selected: detailTab === 'activity' }"
                                @click="detailTab = 'activity'">Обсуждение и история</button><button
                                :class="{ selected: detailTab === 'files' }" @click="detailTab = 'files'">Файлы <span
                                    x-text="selected.attachments.length"></span></button><button
                                :class="{ selected: detailTab === 'time' }"
                                @click="detailTab = 'time'">Трудозатраты</button></div>
                        <div x-show="detailTab === 'activity'">
                            <form class="comment-form" x-show="permissions.includes('comment')"
                                @submit.prevent="sendComment()">
                                <div x-show="replyTo" class="reply-label">Ответ на комментарий <span
                                        x-text="'#'+replyTo"></span><button type="button" @click="replyTo = null"
                                        class="icon-button" aria-label="Отменить ответ" x-html="icon('X')"></button>
                                </div>
                                <textarea aria-label="Комментарий" placeholder="Написать комментарий..." x-model="commentBody" rows="3"
                                    required maxlength="20000"></textarea>
                                <div><label class="icon-button" title="Прикрепить файл"><span
                                            x-html="icon('Paperclip')"></span><input type="file" class="sr-only"
                                            @change="uploadFile($event)" aria-label="Прикрепить файл"></label><button
                                        class="button primary" :disabled="busy || !commentBody.trim()"><span
                                            x-html="icon('Send')"></span>Отправить</button></div>
                            </form>
                            <div class="timeline"><template x-for="entry in activity" :key="entry.kind + entry.id">
                                    <article class="timeline-entry"><span class="avatar small"
                                            :style="avatarStyle(entry.user_id)"
                                            x-text="initials(entry.user?.name)"></span>
                                        <div class="timeline-body">
                                            <div class="timeline-entry-head"><strong
                                                    x-text="entry.user?.name || 'Система'"></strong><small
                                                    x-text="time(entry.created_at)"></small></div><template
                                                x-if="entry.kind === 'comment'">
                                                <div><small class="muted" x-show="entry.parent_id"
                                                        x-text="'Ответ на #'+entry.parent_id"></small>
                                                    <p class="description-text" x-text="entry.body"></p>
                                                    <div class="test-results" x-show="entry.test_result"><template
                                                            x-for="[key,label] in Object.entries({expected:'Ожидаемый результат',actual:'Фактический результат',steps:'Шаги воспроизведения',environment:'Окружение'})">
                                                            <div x-show="entry.test_result?.[key]"><strong
                                                                    x-text="label"></strong>
                                                                <p x-text="entry.test_result?.[key]"></p>
                                                            </div>
                                                        </template></div><button class="text-button"
                                                        x-show="permissions.includes('comment')"
                                                        @click="replyTo = entry.id">Ответить</button><small
                                                        class="muted"
                                                        x-show="entry.updated_at !== entry.created_at">Изменен</small>
                                                </div>
                                            </template><template x-if="entry.kind === 'event'">
                                                <div class="event-body"><span
                                                        x-text="eventName(entry.action)"></span><template
                                                        x-for="[key,change] in Object.entries(entry.changes || {}).filter(([,v]) => v && typeof v === 'object' && 'from' in v)">
                                                        <div class="change-line"><strong
                                                                x-text="fieldName(key)"></strong><span
                                                                x-text="changeValue(key,change.from)"></span><span
                                                                x-html="icon('ArrowRight')"></span><span
                                                                x-text="changeValue(key,change.to)"></span></div>
                                                    </template></div>
                                            </template>
                                        </div>
                                    </article>
                                </template></div>
                        </div>
                        <div x-show="detailTab === 'files'">
                            <div class="file-upload" x-show="permissions.includes('comment')"><label
                                    class="button"><span x-html="icon('Paperclip')"></span>Прикрепить файл<input
                                        type="file" class="sr-only" @change="uploadFile($event)"
                                        aria-label="Загрузить вложение"></label><small class="muted">До 20 МБ на
                                    файл</small></div><template x-for="file in selected.attachments"><a
                                    class="file-row" :href="'/api/v1/attachments/' + file.id"><span
                                        x-html="icon('FileText')"></span>
                                    <div><strong x-text="file.name"></strong><small
                                            x-text="(file.size/1024).toFixed(1)+' КБ'"></small></div><span
                                        x-html="icon('ArrowDownToLine')"></span>
                                </a></template>
                            <p class="empty-cell" x-show="!selected.attachments.length">Нет вложений</p>
                        </div>
                        <div x-show="detailTab === 'time'"><template x-for="log in selected.worklogs">
                                <div class="worklog-row">
                                    <div><strong x-text="log.user?.name"></strong><small
                                            x-text="date(log.date,true)"></small>
                                        <p x-text="log.comment"></p>
                                    </div><strong x-text="hours(log.hours)"></strong>
                                </div>
                            </template>
                            <p class="empty-cell" x-show="!selected.worklogs.length">Трудозатраты не зафиксированы</p>
                        </div>
                    </div>
                    <aside class="issue-properties">
                        <h3>Участники</h3><template
                            x-for="[key,label] in Object.entries({reporter:'Постановщик',assignee:'Исполнитель',tester:'Тестировщик'})">
                            <div class="property"><small x-text="label"></small>
                                <button class="person compact" :disabled="!selected[key]"
                                    @click="openProfile(selected[key])"><span class="avatar small"
                                        :style="avatarStyle(selected[key]?.id)"
                                        x-text="initials(selected[key]?.name) || '—'"></span><span
                                        x-text="selected[key]?.name || 'Не назначен'"></span></button>
                            </div>
                        </template>
                        <hr>
                        <h3>Планирование</h3>
                        <div class="property"><small>Срок выполнения</small><strong
                                :class="{ 'text-red': selected.overdue }"
                                x-text="date(selected.due_date,true)"></strong>
                        </div>
                        <div class="property" x-show="selected.planned_start || selected.planned_end"><small>Период
                                работ</small><span
                                x-text="date(selected.planned_start)+' — '+date(selected.planned_end)"></span></div>
                        <button class="text-button"
                            x-show="selected.can_edit && isManager && !['closed','cancelled'].includes(selected.status)"
                            @click="beginSchedule()"><span x-html="icon('CalendarDays')"></span>Запланировать
                            работу</button>
                        <hr>
                        <div class="section-title">
                            <h3>Оценка и время</h3><button class="icon-button" title="Изменить оценку"
                                aria-label="Изменить оценку"
                                x-show="selected.can_estimate && !['closed','cancelled'].includes(selected.status)"
                                @click="beginEstimate()" x-html="icon('Settings')"></button>
                        </div>
                        <div class="time-summary">
                            <div><span>Первоначально</span><strong x-text="hours(selected.estimate)"></strong></div>
                            <div><span>Затрачено</span><strong x-text="hours(selected.spent)"></strong></div>
                            <div><span>Осталось</span><strong x-text="hours(selected.remaining)"></strong></div>
                        </div><button class="button full-width"
                            x-show="(isManager || (selected.assignee_id === user.id && permissions.includes('worklog'))) && !['closed','cancelled'].includes(selected.status)"
                            @click="beginWorklog()"><span x-html="icon('Clock3')"></span>Записать время</button>
                        <hr>
                        <div class="property" x-show="selected.component"><small>Часть системы</small><span
                                x-text="selected.component || 'Не задан'"></span></div>
                        <div class="property" x-show="selected.version || selected.environment"><small>Версия / среда
                                проверки</small><span
                                x-text="[selected.version,selected.environment].filter(Boolean).join(' / ') || 'Не задано'"></span>
                        </div>
                        <div class="tags"><template x-for="tag in selected.tags || []"><span
                                    x-text="tag"></span></template></div><small class="muted"
                            x-text="'Создана '+time(selected.created_at)"></small>
                    </aside>
                </div>
            </div>
        </template>
        <template x-if="modal === 'create'">
            <form @submit.prevent="saveIssue()">
                <h2
                    x-text="issueEditing ? 'Редактировать задачу' : issueForm.parent_id ? 'Новая подзадача' : 'Создать задачу'">
                </h2><label>Название<input x-model="issueForm.title" required maxlength="255"
                        placeholder="Что необходимо сделать?"></label><label>Описание
                    <textarea x-model="issueForm.description" rows="5" required placeholder="Требования и критерии приемки"></textarea>
                </label>
                <div class="form-grid"><label>Проект<select aria-label="Проект" x-model="issueForm.project_id"
                            required :disabled="issueEditing || !!issueForm.parent_id"
                            @change="issueForm.assignee_id = ''; issueForm.tester_id = ''"><template
                                x-for="p in projects">
                                <option :value="p.id" x-text="p.name"></option>
                            </template></select></label><label>Тип<select aria-label="Тип"
                            x-model="issueForm.type"><template x-for="[k,v] in Object.entries(types)">
                                <option :value="k" x-text="v"></option>
                            </template></select></label><label>Приоритет<select aria-label="Приоритет"
                            x-model="issueForm.priority"><template x-for="[k,v] in Object.entries(priorities)">
                                <option :value="k" x-text="v"></option>
                            </template></select></label><label>Срок выполнения<input type="date"
                            x-model="issueForm.due_date"></label><label
                        x-show="!issueEditing && (isManager || permissions.includes('assign'))">Исполнитель<select
                            aria-label="Исполнитель" x-model="issueForm.assignee_id">
                            <option value="">Не назначен</option><template
                                x-for="u in memberOptions.filter(u => u.role === 'developer' && u.active)">
                                <option :value="u.id" x-text="u.name"></option>
                            </template>
                        </select></label><label
                        x-show="!issueEditing && (isManager || permissions.includes('assign'))">Тестировщик<select
                            aria-label="Тестировщик" x-model="issueForm.tester_id">
                            <option value="">Не назначен</option><template
                                x-for="u in memberOptions.filter(u => u.role === 'tester' && u.active)">
                                <option :value="u.id" x-text="u.name"></option>
                            </template>
                        </select></label></div>
                <div class="form-footer"><button type="button" class="button"
                        @click="issueEditing ? modal = 'issue' : closeModal()">Отмена</button><button
                        class="button primary" :disabled="busy"><span x-html="icon('Check')"></span><span
                            x-text="issueEditing ? 'Сохранить' : 'Создать задачу'"></span></button></div>
            </form>
        </template>
        <template x-if="modal === 'transition'">
            <form @submit.prevent="saveTransition()">
                <h2 x-text="actionName(transitionStatus)"></h2>
                <p class="muted" x-text="selected?.key+' · '+selected?.title"></p><label><span
                        x-text="['returned','blocked'].includes(transitionStatus) ? 'Причина (обязательно)' : 'Комментарий'"></span>
                    <textarea rows="4" x-model="reason" :required="['returned', 'blocked'].includes(transitionStatus)"
                        maxlength="10000"></textarea>
                </label>
                <details x-show="transitionStatus === 'returned'">
                    <summary>Подробности проверки</summary><template
                        x-for="[key,label] in Object.entries({expected:'Ожидаемый результат',actual:'Фактический результат',steps:'Шаги воспроизведения',environment:'Окружение'})"><label><span
                                x-text="label"></span>
                            <textarea rows="2" x-model="testResult[key]"></textarea>
                        </label></template>
                </details>
                <div class="form-footer"><button type="button" class="button"
                        @click="modal = 'issue'">Отмена</button><button class="button primary"
                        :disabled="busy">Подтвердить переход</button></div>
            </form>
        </template>
        <template x-if="modal === 'estimate'">
            <form @submit.prevent="saveEstimate()">
                <h2>Оценка задачи</h2>
                <div class="form-grid"><label>Первоначальная оценка, ч<input type="number" step="0.25"
                            min="0" max="100000" x-model.number="form.estimate"
                            required></label><label>Остаточная оценка, ч<input type="number" step="0.25"
                            min="0" max="100000" x-model.number="form.remaining" required></label></div>
                <label>Техническое решение
                    <textarea rows="5" x-model="form.solution"></textarea>
                </label>
                <div class="form-footer"><button type="button" class="button"
                        @click="modal = 'issue'">Отмена</button><button class="button primary"
                        :disabled="busy">Сохранить оценку</button></div>
            </form>
        </template>
        <template x-if="modal === 'worklog'">
            <form @submit.prevent="saveWorklog()">
                <h2>Записать время</h2>
                <div class="time-presets"><template x-for="h in [0.5,1,2,4]"><button class="button" type="button"
                            :class="{ selected: worklog.hours === h }" @click="worklog.hours = h"
                            x-text="hours(h)"></button></template></div>
                <div class="form-grid"><label>Затрачено, ч<input type="number" min="0.01" max="24"
                            step="0.01" required x-model.number="worklog.hours"></label><label>Дата<input
                            type="date" required x-model="worklog.date"></label></div><label>Комментарий
                    <textarea rows="3" x-model="worklog.comment"></textarea>
                </label><label>Осталось после этой работы, ч<input type="number" min="0" max="100000"
                        step="0.25" required x-model.number="worklog.remaining"></label>
                <div class="form-footer"><button type="button" class="button"
                        @click="modal = 'issue'">Отмена</button><button class="button primary"
                        :disabled="busy">Сохранить время</button></div>
            </form>
        </template>
        @include('partials.workspace-modals')
        <template x-if="modal === 'user'">
            <form @submit.prevent="saveUser()">
                <h2 x-text="form.id ? 'Редактировать пользователя' : 'Новый пользователь'"></h2>
                <div class="form-grid"><label>ФИО<input x-model="form.name" required></label><label>Учетная
                        запись<input x-model="form.username" required
                            pattern="[a-zA-Z0-9_-]+"></label><label>Email<input type="email" x-model="form.email"
                            required></label></div><label>Пароль<input type="password" autocomplete="new-password"
                        x-model="form.password" :required="!form.id" minlength="12"
                        placeholder="12+ символов, A–Z, a–z, цифры и спецсимвол"></label>
                <div class="form-grid"><label>Роль<select x-model="form.role"><template
                                x-for="[k,v] in Object.entries(roles)">
                                <option :value="k" x-text="v"></option>
                            </template></select></label><label>Должность<input
                            x-model="form.position"></label><label>Подразделение<input
                            x-model="form.department"></label><label>Емкость в неделю, ч<input type="number"
                            min="0" max="168" step="0.5" x-model.number="form.weekly_capacity"
                            required></label><label>WIP-limit<input type="number" min="1" max="50"
                            x-model.number="form.wip_limit" required></label><label class="checkbox"><input
                            type="checkbox" x-model="form.active">Учетная запись активна</label></div>
                <h3>Рабочие дни</h3>
                <div class="checkbox-group"><template
                        x-for="[i,day] in ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'].entries()"><label
                            class="checkbox"><input type="checkbox" :checked="form.work_days.includes(i + 1)"
                                @change="form.work_days = $event.target.checked ? [...form.work_days,i+1] : form.work_days.filter(d => d !== i+1)"><span
                                x-text="day"></span></label></template></div>
                <h3>Команды</h3>
                <div class="checkbox-group"><template x-for="t in teams"><label class="checkbox"><input
                                type="checkbox" :value="t.id" x-model.number="form.team_ids"><span
                                x-text="t.name"></span></label></template></div>
                <h3>Проекты</h3>
                <div class="checkbox-group"><template x-for="p in projects"><label class="checkbox"><input
                                type="checkbox" :value="p.id" x-model.number="form.project_ids"><span
                                x-text="p.name"></span></label></template></div>
                <div class="form-footer"><button type="button" class="button"
                        @click="closeModal()">Отмена</button><button class="button primary"
                        :disabled="busy">Сохранить пользователя</button></div>
            </form>
        </template>
        <template x-if="modal === 'project'">
            <form @submit.prevent="saveProject()">
                <h2 x-text="form.id ? 'Настройки проекта' : 'Новый проект'"></h2>
                <div class="form-grid"><label>Название<input x-model="form.name" required></label><label>Ключ
                        проекта<input x-model="form.key" pattern="[A-Z][A-Z0-9]{1,12}"
                            required></label><label>Команда<select x-model.number="form.team_id" required><template
                                x-for="t in teams">
                                <option :value="t.id" x-text="t.name"></option>
                            </template></select></label><label>Ответственный<select x-model.number="form.owner_id"
                            required><template x-for="u in users">
                                <option :value="u.id" x-text="u.name"></option>
                            </template></select></label><label>Статус<select x-model="form.status">
                            <option value="active">Активен</option>
                            <option value="paused">Приостановлен</option>
                            <option value="archived">В архиве</option>
                        </select></label><label>Цвет<input type="color" x-model="form.color"></label></div>
                <label>Описание
                    <textarea rows="3" x-model="form.description"></textarea>
                </label>
                <h3>Участники проекта</h3>
                <div class="checkbox-group members-checkbox"><template x-for="u in users"><label
                            class="checkbox"><input type="checkbox" :value="u.id"
                                x-model.number="form.member_ids"><span x-text="u.name"></span></label></template>
                </div>
                <div class="form-footer"><button type="button" class="button"
                        @click="closeModal()">Отмена</button><button class="button primary"
                        :disabled="busy">Сохранить проект</button></div>
            </form>
        </template>
        <template x-if="modal === 'team'">
            <form @submit.prevent="saveTeam()">
                <h2>Новая команда</h2><label>Название<input x-model="form.name"
                        required></label><label>Руководитель<select x-model.number="form.leader_id" required>
                        <option value="">Выберите руководителя</option><template
                            x-for="u in adminData.users.filter(u => ['admin','manager'].includes(u.role) && u.active)">
                            <option :value="u.id" x-text="u.name"></option>
                        </template>
                    </select></label>
                <div class="form-footer"><button class="button primary" :disabled="busy">Создать
                        команду</button></div>
            </form>
        </template>
        <template x-if="modal === 'calendar'">
            <form @submit.prevent="saveCalendar()">
                <h2>Рабочий день</h2><label>Сотрудник<select x-model.number="form.user_id">
                        <option value="">Все сотрудники (праздник)</option><template
                            x-for="u in adminData.users">
                            <option :value="u.id" x-text="u.name"></option>
                        </template>
                    </select></label>
                <div class="form-grid"><label>Дата<input type="date" required
                            x-model="form.date"></label><label>Доступно часов<input type="number" min="0"
                            max="24" step="0.5" required x-model.number="form.hours"></label></div>
                <label>Причина<input x-model="form.reason" required
                        placeholder="Отпуск, праздник, отсутствие"></label>
                <div class="form-footer"><button class="button primary" :disabled="busy">Сохранить
                        день</button></div>
            </form>
        </template>
        <template x-if="modal === 'filter'">
            <form @submit.prevent="saveFilter()">
                <h2>Сохранить фильтр</h2><label>Название<input required x-model="filterName" maxlength="80"></label>
                <div class="form-footer"><button class="button primary" :disabled="busy">Сохранить</button>
                </div>
            </form>
        </template>
        <template x-if="modal === 'preferences'">
            <form @submit.prevent="savePreferences()">
                <h2>Типы уведомлений</h2>
                <div class="preference-list"><template
                        x-for="[type,name] in Object.entries({created:'Создание задачи',updated:'Изменение задачи и назначения',status_changed:'Смена статуса и возврат QA',commented:'Комментарии и упоминания',work_logged:'Записи времени',file_uploaded:'Новые вложения',due_soon:'Приближение срока',overdue:'Просрочка'})"><label
                            class="checkbox"><input type="checkbox" :checked="form[type] !== false"
                                @change="form[type] = $event.target.checked"><span
                                x-text="name"></span></label></template></div>
                <div class="form-footer"><button class="button primary" :disabled="busy">Сохранить</button>
                </div>
            </form>
        </template>
    </section>
</div>
