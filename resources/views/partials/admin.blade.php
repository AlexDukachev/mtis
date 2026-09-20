<section x-show="view === 'admin' && user.role === 'admin'">
    <div class="section-toolbar">
        <div class="filter-tabs"><template
                x-for="[id,name] in Object.entries({users:'Пользователи',teams:'Команды',settings:'Настройки и права',calendar:'Рабочий календарь',audit:'Аудит'})"><button
                    :class="{ selected: adminTab === id }" @click="adminTab = id" x-text="name"></button></template>
        </div>
        <button class="button primary" x-show="adminTab === 'users'" @click="openUser()"><span
                x-html="icon('UserPlus')"></span>Пользователь</button>
    </div>
    <div class="data-table-wrap" x-show="adminTab === 'users'">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Пользователь</th>
                    <th>Email</th>
                    <th>Роль</th>
                    <th>Емкость</th>
                    <th>Статус</th>
                    <th></th>
                </tr>
            </thead>
            <tbody><template x-for="u in adminData.users">
                    <tr @click="openUser(u)">
                        <td>
                            <div class="person"><span class="avatar" :style="avatarStyle(u.id)"
                                    x-text="initials(u.name)"></span><strong x-text="u.name"></strong></div>
                        </td>
                        <td x-text="u.email"></td>
                        <td x-text="roles[u.role]"></td>
                        <td x-text="hours(u.weekly_capacity)"></td>
                        <td><span class="status-badge" :class="u.active ? 'status-closed' : 'status-cancelled'"
                                x-text="u.active ? 'Активен' : 'Отключен'"></span></td>
                        <td x-html="icon('ChevronRight')"></td>
                    </tr>
                </template></tbody>
        </table>
    </div>
    <div x-show="adminTab === 'teams'">
        <div class="section-title">
            <h2>Команды</h2><button class="button" @click="form = {name:'',leader_id:''}; openModal('team')"><span
                    x-html="icon('Plus')"></span>Новая команда</button>
        </div>
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Команда</th>
                        <th>Руководитель</th>
                        <th>Участников</th>
                    </tr>
                </thead>
                <tbody><template x-for="team in adminData.teams">
                        <tr>
                            <td x-text="team.name"></td>
                            <td x-text="adminData.users.find(u => u.id === team.leader_id)?.name"></td>
                            <td x-text="team.members.length"></td>
                        </tr>
                    </template></tbody>
            </table>
        </div>
    </div>
    <form x-show="adminTab === 'settings'" @submit.prevent="saveSettings()">
        <h2>Пороговые значения загрузки</h2>
        <div class="form-grid settings-thresholds"><label>Резерв до, %<input type="number" min="0"
                    max="100" x-model.number="adminData.thresholds.reserve"></label><label>Перегрузка выше, %<input
                    type="number" min="0" max="500" x-model.number="adminData.thresholds.overload"></label>
        </div>
        <h2>Разрешения ролей</h2>
        <div class="data-table-wrap">
            <table class="data-table permissions-table">
                <thead>
                    <tr>
                        <th>Действие</th><template
                            x-for="role in ['manager','reporter','developer','tester','observer']">
                            <th x-text="roles[role]"></th>
                        </template>
                    </tr>
                </thead>
                <tbody><template
                        x-for="[permission,name] in Object.entries({create:'Создание задач',comment:'Комментарии и файлы',assign:'Назначение исполнителя',estimate:'Оценка своих задач',develop:'Разработка своих задач',test:'Тестирование',accept:'Приемка своих задач',reopen:'Переоткрытие своих задач',reports:'Отчеты руководителя',worklog:'Учет своего времени'})">
                        <tr>
                            <td x-text="name"></td><template
                                x-for="role in ['manager','reporter','developer','tester','observer']">
                                <td><input type="checkbox" :aria-label="name + ': ' + roles[role]"
                                        :checked="adminData.permissions[role]?.includes(permission)"
                                        @change="adminData.permissions[role] = $event.target.checked ? [...(adminData.permissions[role] || []),permission] : (adminData.permissions[role] || []).filter(p => p !== permission)">
                                </td>
                            </template>
                        </tr>
                    </template></tbody>
            </table>
        </div><button class="button primary" :disabled="busy"><span x-html="icon('Save')"></span>Сохранить
            настройки</button>
    </form>
    <div x-show="adminTab === 'calendar'">
        <div class="section-title">
            <h2>Исключения рабочего календаря</h2><button class="button"
                @click="form = {user_id:'',date:'',hours:0,reason:''}; openModal('calendar')"><span
                    x-html="icon('Plus')"></span>Добавить день</button>
        </div>
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Сотрудник</th>
                        <th>Доступно часов</th>
                        <th>Причина</th>
                    </tr>
                </thead>
                <tbody><template x-for="entry in adminData.calendar">
                        <tr>
                            <td x-text="date(entry.date,true)"></td>
                            <td x-text="adminData.users.find(u => u.id === entry.user_id)?.name || 'Все сотрудники'">
                            </td>
                            <td x-text="hours(entry.hours)"></td>
                            <td x-text="entry.reason"></td>
                        </tr>
                    </template>
                    <tr x-show="!adminData.calendar.length">
                        <td colspan="4" class="empty-cell">Исключений пока нет</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div x-show="adminTab === 'audit'" class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Время</th>
                    <th>Пользователь</th>
                    <th>Действие</th>
                    <th>Объект / изменения</th>
                </tr>
            </thead>
            <tbody><template x-for="event in adminData.events">
                    <tr>
                        <td x-text="time(event.created_at)"></td>
                        <td x-text="event.user?.name || 'Система'"></td>
                        <td x-text="eventName(event.action)"></td>
                        <td class="audit-detail" x-text="JSON.stringify(event.changes || {})"></td>
                    </tr>
                </template></tbody>
        </table>
    </div>
</section>
