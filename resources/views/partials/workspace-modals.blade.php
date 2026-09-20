<template x-if="modal === 'assign'">
    <form @submit.prevent="saveAssignment()">
        <h2>Назначить исполнителей</h2>
        <p class="muted" x-text="selected.key+' · '+selected.title"></p>
        <template
            x-for="[field,role,label] in [['assignee_id','developer','Разработчик'],['tester_id','tester','Тестировщик']]">
            <label><span x-text="label"></span><select x-model="form[field]" :aria-label="label">
                    <option value="">Не назначен</option><template
                        x-for="u in (projects.find(p=>p.id===selected.project_id)?.members || []).filter(u=>u.active && u.role===role)">
                        <option :value="u.id" x-text="u.name"></option>
                    </template>
                </select></label>
        </template>
        <div class="form-footer"><button type="button" class="button" @click="modal='issue'">Отмена</button><button
                class="button primary" :disabled="busy">Сохранить назначение</button></div>
    </form>
</template>
<template x-if="modal === 'start'">
    <form @submit.prevent="saveTransition()">
        <h2>Начать работу</h2>
        <p class="muted" x-text="selected.key+' · '+selected.title"></p>
        <label>Сколько часов потребуется?<input type="number" required min="0" max="100000" step="0.25"
                x-model.number="startEstimate"></label>
        <div class="form-footer"><button type="button" class="button" @click="modal='issue'">Отмена</button><button
                class="button primary" :disabled="busy"><span x-html="icon('Play')"></span>Начать работу</button>
        </div>
    </form>
</template>
<template x-if="modal === 'schedule'">
    <form @submit.prevent="saveSchedule()">
        <h2>План работ</h2>
        <p class="muted" x-text="selected.key+' · '+selected.title"></p>
        <div class="form-grid"><label>Плановый старт<input type="date" x-model="form.planned_start"
                    required></label><label>Плановое завершение<input type="date" x-model="form.planned_end"
                    :min="form.planned_start" required></label></div>
        <div class="form-footer"><button type="button" class="button" @click="modal='issue'">Отмена</button><button
                class="button primary" :disabled="busy">Сохранить план</button></div>
    </form>
</template>
<template x-if="modal === 'profile' && profileUser">
    <div>
        <div class="person profile-heading"><span class="avatar" :style="avatarStyle(profileUser.id)"
                x-text="initials(profileUser.name)"></span>
            <div>
                <h2 x-text="profileUser.name"></h2><span class="muted" x-text="roles[profileUser.role]"></span>
            </div>
        </div>
        <div class="profile-facts"><span x-text="profileUser.position"></span><span
                x-text="profileUser.department"></span><span
                x-text="(profileUser.teams || []).map(t=>t.name).join(', ')"></span><span
                x-text="'Рабочая емкость: '+hours(profileUser.weekly_capacity)+' в неделю'"></span></div>
        <form x-show="profileUser.id === user.id" @submit.prevent="saveProfile()">
            <label>ФИО<input x-model="form.name" required maxlength="255"></label><label>Email<input type="email"
                    x-model="form.email" required maxlength="255"></label>
            <label>Оформление<select aria-label="Оформление" x-model="form.theme">
                    <option value="system">Как в системе</option>
                    <option value="light">Светлое</option>
                    <option value="dark">Темное</option>
                </select></label>
            <details>
                <summary>Сменить пароль</summary><label>Новый пароль<input type="password" x-model="form.password"
                        minlength="12" autocomplete="new-password"></label><label>Повторите новый пароль<input
                        type="password" x-model="form.password_confirmation" autocomplete="new-password"></label>
            </details>
            <label x-show="form.password || form.email !== user.email">Текущий пароль<input type="password"
                    x-model="form.current_password" autocomplete="current-password"
                    :required="!!form.password || form.email !== user.email"></label>
            <div class="form-footer"><button type="button" class="button"
                    @click="modal=profileReturn">Назад</button><button class="button primary"
                    :disabled="busy">Сохранить профиль</button></div>
        </form>
        <div x-show="profileUser.id !== user.id"><a class="text-button" :href="'mailto:' + profileUser.email"
                x-text="profileUser.email"></a>
            <div class="form-footer"><button class="button" @click="modal=profileReturn">Назад</button><button
                    class="button primary"
                    @click="closeModal(); go('issues',{assignee_id:String(profileUser.id)})">Задачи сотрудника</button>
            </div>
        </div>
    </div>
</template>
<template x-if="modal === 'teamMembers'">
    <form @submit.prevent="saveTeamMembers()">
        <h2>Состав команды</h2><label>Название<input x-model="form.name" required maxlength="255"></label>
        <div class="member-checklist"><template x-for="u in users"><label class="checkbox"><input type="checkbox"
                        x-model.number="form.member_ids" :value="u.id"
                        :disabled="u.id === form.leader_id"><span x-text="u.name"></span><small class="muted"
                        x-text="roles[u.role]"></small></label></template></div>
        <div class="form-footer"><button type="button" class="button" @click="closeModal()">Отмена</button><button
                class="button primary" :disabled="busy">Сохранить команду</button></div>
    </form>
</template>
<template x-if="modal === 'roadmap'">
    <form @submit.prevent="saveRoadmap()">
        <h2 x-text="form.id ? 'Изменить план' : 'Крупная работа'"></h2>
        <label>Название<input x-model="form.title" required maxlength="255"></label>
        <label>Проект<select aria-label="Проект" x-model.number="form.project_id" :disabled="!!form.id" required
                @change="form.developer_ids=[]">
                <option value="">Выберите проект</option><template
                    x-for="p in projects.filter(p=>user.role==='admin'||teams.some(t=>t.id===p.team_id&&t.leader_id===user.id))">
                    <option :value="p.id" x-text="p.name"></option>
                </template>
            </select></label>
        <div class="form-grid"><label>Сдать до<input type="date" required
                    x-model="form.deadline"></label><label>Длительность, рабочих дней<input type="number" required
                    min="1" max="520" x-model.number="form.duration_days"></label><label>Разработчиков на
                полный день<input type="number" required min="1" max="100"
                    x-model.number="form.required_people"></label><label>Состояние<select aria-label="Состояние"
                    x-model="form.status"><template x-for="[key,label] in Object.entries(roadmapStatuses)">
                        <option :value="key" x-text="label"></option>
                    </template></select></label></div>
        <h3>Запланированные исполнители</h3>
        <div class="member-checklist"><template
                x-for="u in (projects.find(p=>p.id==form.project_id)?.members || []).filter(u=>u.active&&u.role==='developer')"><label
                    class="checkbox"><input type="checkbox" x-model.number="form.developer_ids"
                        :value="u.id"><span x-text="u.name"></span><small class="muted"
                        x-text="hours(u.weekly_capacity)+'/нед.'"></small></label></template></div>
        <details>
            <summary>Требования и результат</summary><label>Описание
                <textarea rows="4" x-model="form.description"></textarea>
            </label>
        </details>
        <div class="form-footer"><button type="button" class="button" @click="closeModal()">Отмена</button><button
                class="button primary" :disabled="busy">Сохранить план</button></div>
    </form>
</template>
