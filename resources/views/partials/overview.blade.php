<section x-show="view === 'dashboard'">
    <div class="stats-grid"><template
            x-for="metric in [{title:'Активные задачи',value:dashboard.active,icon:'ListTodo',filter:{}},{title:'Без исполнителя',value:dashboard.unassigned,icon:'Users',filter:{unassigned:'1'}},{title:'Просрочено',value:dashboard.overdue,icon:'Clock3',filter:{overdue:'1'}},{title:'Ожидают QA',value:dashboard.statuses.qa || 0,icon:'CircleCheck',filter:{status:'qa'}}]"><button
                class="stat" @click="go('issues',metric.filter)">
                <div class="stat-label"><span x-text="metric.title"></span><span class="stat-icon green"
                        x-html="icon(metric.icon)"></span></div>
                <div class="stat-value" x-text="metric.value || 0"></div>
                <div class="stat-foot">Открыть задачи <span x-html="icon('ArrowUpRight')"></span></div>
            </button></template></div>
    <div class="lower-grid overview-grid">
        <section>
            <div class="section-title">
                <h2>Задачи по статусам</h2>
            </div>
            <div class="status-chart"><template
                    x-for="[key,name] in Object.entries(statuses).filter(([k]) => dashboard.statuses[k])"><button
                        @click="go('issues',{status:key})"><span x-text="name"></span><span class="chart-track"><i
                                :class="'chart-' + key"
                                :style="'width:' + Math.max(4, (dashboard.statuses[key] / Math.max(1, dashboard.active)) *
                                    100) + '%'"></i></span><strong
                            x-text="dashboard.statuses[key]"></strong></button></template></div>
        </section>
        <section>
            <div class="section-title">
                <h2>Активность команды</h2><span class="online-dot"></span>
            </div>
            <div class="activity-list"><template x-for="event in dashboard.events.slice(0,8)"
                    :key="event.id"><button class="activity-row" @click="openIssue(event.issue_id)"><span
                            class="avatar small" :style="avatarStyle(event.user_id)"
                            x-text="initials(event.user?.name)"></span>
                        <div><strong x-text="event.user?.name"></strong><span
                                x-text="eventName(event.action)"></span><small><span x-text="event.issue?.key"></span> ·
                                <span x-text="time(event.created_at)"></span></small></div><span class="status-badge"
                            x-show="event.changes?.status" :class="'status-' + event.changes?.status?.to"
                            x-text="statuses[event.changes?.status?.to]"></span>
                    </button></template></div>
        </section>
    </div>
</section>
<section x-show="view === 'projects'">
    <div class="section-toolbar">
        <h2 x-text="projects.length + ' проектов'"></h2><button class="button" x-show="isManager"
            @click="openProject()"><span x-html="icon('Plus')"></span>Новый проект</button>
    </div>
    <div class="projects-grid"><template x-for="project in projects" :key="project.id">
            <article class="project-card">
                <div class="project-card-head"><span class="project-monogram"
                        :style="'background:' + project.color + '18;color:' + project.color"
                        x-text="project.key.slice(0,2)"></span><span class="status-badge"
                        :class="project.status === 'active' ? 'status-closed' : 'status-deferred'"
                        x-text="({active:'Активен',paused:'Приостановлен',archived:'В архиве'})[project.status]"></span><button
                        x-show="isManager" class="icon-button" title="Настройки проекта" aria-label="Настройки проекта"
                        @click="openProject(project)" x-html="icon('Settings')"></button></div>
                <h2><button @click="go('issues',{project_id:String(project.id)})" x-text="project.name"></button></h2>
                <p class="muted" x-text="project.description"></p>
                <div class="project-counts"><span><strong x-text="project.issues_count"></strong>
                        задач</span><span><strong x-text="project.members.length"></strong> участников</span></div>
                <div class="project-card-foot">
                    <div class="avatar-stack"><template x-for="member in project.members.slice(0,5)"><span
                                class="avatar small" :style="avatarStyle(member.id)" :title="member.name"
                                x-text="initials(member.name)"></span></template></div><button class="text-button"
                        @click="go('issues',{project_id:String(project.id)})">Задачи<span
                            x-html="icon('ArrowRight')"></span></button>
                </div>
            </article>
        </template></div>
</section>
<section x-show="view === 'team'">
    <div class="section-toolbar">
        <div class="actions"><select aria-label="Команда сотрудников" x-model="rosterTeam" @change="rosterPage=1">
                <option value="">Все сотрудники</option><template x-for="t in teams">
                    <option :value="t.id" x-text="t.name"></option>
                </template>
            </select><input aria-label="Поиск сотрудника" placeholder="Имя или должность" x-model="teamSearch"
                @input="rosterPage=1"></div><button class="button"
            x-show="selectedTeam && (user.role==='admin' || selectedTeam.leader_id===user.id)"
            @click="editTeamMembers()"><span x-html="icon('Users')"></span>Изменить состав</button>
    </div>
    <div class="team-summary" x-show="selectedTeam">
        <div><small class="muted">Руководитель</small><button class="text-button"
                @click="openProfile(users.find(u=>u.id===selectedTeam?.leader_id))"
                x-text="users.find(u=>u.id===selectedTeam?.leader_id)?.name"></button></div>
        <div class="actions"><template x-for="p in projects.filter(p=>p.team_id==rosterTeam)"><button
                    class="text-button" @click="go('issues',{project_id:String(p.id)})"
                    x-text="p.name"></button></template><button class="button" x-show="isManager"
                @click="teamId=rosterTeam; go('workload')"><span x-html="icon('ChartNoAxesCombined')"></span>Загрузка
                команды</button></div>
    </div>
    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Сотрудник</th>
                    <th>Роль</th>
                    <th>Команда</th>
                    <th>Email</th>
                    <th>Емкость в неделю</th>
                    <th>Лимит задач в работе</th>
                </tr>
            </thead>
            <tbody><template x-for="u in roster.slice((rosterPage-1)*25,rosterPage*25)" :key="u.id">
                    <tr>
                        <td>
                            <button class="person" @click="openProfile(u)"><span class="avatar"
                                    :style="avatarStyle(u.id)" x-text="initials(u.name)"></span>
                                <div><strong x-text="u.name"></strong><small x-text="u.position"></small></div>
                            </button>
                        </td>
                        <td x-text="roles[u.role]"></td>
                        <td x-text="u.teams.map(t => t.name).join(', ')"></td>
                        <td x-text="u.email"></td>
                        <td x-text="hours(u.weekly_capacity)"></td>
                        <td x-text="u.wip_limit"></td>
                    </tr>
                </template></tbody>
        </table>
    </div>
    <div class="pagination"><span x-text="roster.length+' сотрудников'"></span>
        <div><button class="icon-button" aria-label="Предыдущие сотрудники" :disabled="rosterPage <= 1"
                @click="rosterPage--" x-html="icon('ChevronLeft')"></button><span
                x-text="rosterPage+' / '+Math.max(1,Math.ceil(roster.length/25))"></span><button class="icon-button"
                aria-label="Следующие сотрудники" :disabled="rosterPage * 25 >= roster.length" @click="rosterPage++"
                x-html="icon('ChevronRight')"></button></div>
    </div>
</section>
<section x-show="view === 'reports'">
    <div class="stats-grid">
        <div class="stat">
            <div class="stat-label">Первоначальная оценка</div>
            <div class="stat-value"><span x-text="dashboard.estimate"></span><small>ч</small></div>
        </div>
        <div class="stat">
            <div class="stat-label">Фактические трудозатраты</div>
            <div class="stat-value"><span x-text="dashboard.spent"></span><small>ч</small></div>
        </div>
        <div class="stat">
            <div class="stat-label">Остаточная оценка</div>
            <div class="stat-value"><span x-text="dashboard.remaining"></span><small>ч</small></div>
        </div>
        <div class="stat">
            <div class="stat-label">Просроченные задачи</div>
            <div class="stat-value" x-text="dashboard.overdue"></div>
        </div>
    </div>
    <div class="section-title">
        <h2>По проектам</h2><span class="muted">За все время</span>
    </div>
    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Проект</th>
                    <th>Создано</th>
                    <th>Закрыто</th>
                    <th>Активные</th>
                    <th>QA</th>
                    <th>Просрочено</th>
                    <th>План</th>
                    <th>Факт</th>
                    <th>Осталось</th>
                </tr>
            </thead>
            <tbody><template x-for="row in dashboard.projects">
                    <tr @click="go('issues',{project_id:String(row.project.id)})">
                        <td><span class="project-label"><i :style="'background:' + row.project.color"></i><strong
                                    x-text="row.project.name"></strong></span></td>
                        <td x-text="row.total"></td>
                        <td x-text="row.closed"></td>
                        <td x-text="row.active"></td>
                        <td x-text="row.qa"></td>
                        <td :class="{ 'text-red': row.overdue }" x-text="row.overdue"></td>
                        <td x-text="hours(row.estimate)"></td>
                        <td x-text="hours(row.spent)"></td>
                        <td x-text="hours(row.remaining)"></td>
                    </tr>
                </template></tbody>
        </table>
    </div>
    <div class="report-note"><span x-html="icon('CircleAlert')"></span>Разница плана и факта требует анализа причин:
        изменения требований, зависимостей и первоначальной оценки.</div>
    <div class="section-title">
        <h2>Загрузка команды</h2><select aria-label="Период отчета" x-model="period" @change="refreshWorkload()">
            <option value="week">Эта неделя</option>
            <option value="next">Следующая неделя</option>
            <option value="fortnight">Две недели</option>
            <option value="month">Месяц</option>
        </select>
    </div>
    <div class="report-load"><template x-for="row in workload.rows">
            <div><strong x-text="row.user.name"></strong><span class="meter-track"><span :class="row.state"
                        :style="'width:' + Math.min(row.percent || 0, 100) + '%'"></span></span><span
                    x-text="hours(row.hours)+' / '+hours(row.capacity)"></span><span
                    :class="{ 'text-amber': row.incomplete }"
                    x-text="row.percent === null ? stateName(row.state) : row.percent+'%'"></span></div>
        </template></div>
</section>
<section x-show="view === 'notifications'">
    <div class="section-toolbar"><span class="muted" x-text="unread + ' непрочитанных'"></span>
        <div class="actions"><button class="button"
                @click="form = {...user.notification_preferences}; openModal('preferences')"><span
                    x-html="icon('Settings')"></span>Настройки</button><button class="button"
                @click="readAll()"><span x-html="icon('Check')"></span>Прочитать все</button></div>
    </div>
    <div class="notification-list"><template x-for="n in notifications" :key="n.id"><button
                class="notification-row" :class="{ 'unread': !n.read_at }"
                @click="run(() => readNotification(n))"><span class="attention-icon"
                    :class="n.read_at ? 'blue' : 'green'" x-html="icon('Bell')"></span>
                <div><strong><span x-text="n.issue?.key"></span> · <span
                            x-text="eventName(n.type)"></span></strong><span
                        x-text="n.issue?.title || n.title"></span><small x-text="time(n.created_at)"></small></div><i
                    class="online-dot" x-show="!n.read_at"></i><span x-html="icon('ChevronRight')"></span>
            </button></template>
        <div class="empty-state" x-show="!notifications.length"><span x-html="icon('Bell')"></span>
            <h3>Нет уведомлений</h3>
        </div>
    </div>
    <div class="pagination"><span x-text="(pagination.total || 0)+' уведомлений'"></span>
        <div><button class="icon-button" aria-label="Предыдущие уведомления" :disabled="page <= 1 || busy"
                @click="page--; run(()=>loadView())" x-html="icon('ChevronLeft')"></button><span
                x-text="page+' / '+(pagination.last_page || 1)"></span><button class="icon-button"
                aria-label="Следующие уведомления" :disabled="page >= pagination.last_page || busy"
                @click="page++; run(()=>loadView())" x-html="icon('ChevronRight')"></button></div>
    </div>
</section>
