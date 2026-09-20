<section x-show="view === 'roadmap'">
    <div class="section-toolbar roadmap-toolbar">
        <div class="actions"><button class="icon-button" title="Предыдущий год" aria-label="Предыдущий год"
                :disabled="roadmapYear <= 2020" @click="roadmapYear--; roadmapPage=1; run(()=>loadRoadmap())"
                x-html="icon('ChevronLeft')"></button><input class="year-input" aria-label="Год дорожной карты"
                type="number" min="2020" max="2100" x-model.number="roadmapYear"
                @change="roadmapPage=1; run(()=>loadRoadmap())"><button class="icon-button" title="Следующий год"
                aria-label="Следующий год" :disabled="roadmapYear >= 2100"
                @click="roadmapYear++; roadmapPage=1; run(()=>loadRoadmap())"
                x-html="icon('ChevronRight')"></button><select aria-label="Проект дорожной карты"
                x-model="roadmapProject" @change="roadmapPage=1; run(()=>loadRoadmap())">
                <option value="">Все проекты</option><template x-for="p in projects">
                    <option :value="p.id" x-text="p.name"></option>
                </template>
            </select><label class="checkbox"><input type="checkbox" x-model="roadmapArchived" @change="roadmapPage=1; run(()=>loadRoadmap())">Включая завершенные</label></div>
        <button class="button primary" x-show="isManager" @click="editRoadmap()"><span
                x-html="icon('Plus')"></span>Добавить работу</button>
    </div>
    <div class="stats-grid">
        <div class="stat">
            <div class="stat-label">Крупные работы</div>
            <div class="stat-value" x-text="roadmap.total"></div>
        </div>
        <div class="stat">
            <div class="stat-label">Требуют внимания</div>
            <div class="stat-value text-amber" x-text="roadmap.at_risk"></div>
        </div>
    </div>
    <div class="roadmap-scroll">
        <div class="roadmap-chart">
            <div class="roadmap-header"><strong>Работа / срок сдачи</strong>
                <div class="roadmap-months"><template x-for="month in months"><span x-text="month"></span></template>
                </div>
            </div>
            <template x-for="item in roadmap.data" :key="item.id">
                <article class="roadmap-row">
                    <div class="roadmap-label"><span class="project-label"><i
                                :style="'background:' + item.project.color"></i><span
                                x-text="item.project.name"></span></span><button class="roadmap-title"
                            :disabled="!item.can_edit" @click="editRoadmap(item)"
                            x-text="item.title"></button><small><span x-text="roadmapStatuses[item.status]"></span> ·
                            <span x-text="date(item.deadline,true)"></span></small>
                        <small class="roadmap-mobile-start" x-text="'Начать до '+date(item.latest_start,true)"></small>
                        <div class="roadmap-people"><span x-html="icon('Users')"></span><span
                                x-text="item.assigned_fte+' / '+item.required_people+' чел.'"></span><span
                                x-text="item.duration_days+' раб. дн.'"></span><button class="icon-button"
                                x-show="item.can_edit" title="Изменить план" aria-label="Изменить план"
                                @click="editRoadmap(item)" x-html="icon('Settings')"></button></div><template
                            x-for="risk in item.risks"><small class="roadmap-risk"><span
                                    x-html="icon('TriangleAlert')"></span><span
                                    x-text="risk"></span></small></template>
                    </div>
                    <div class="roadmap-track">
                        <div class="roadmap-bar" :class="{ 'risk': item.risks.length, 'finished': item.status === 'done' }"
                            :style="roadmapBar(item)"
                            :title="'Начать не позднее ' + date(item.latest_start, true) + '; сдать ' + date(item.deadline,
                                true)">
                        </div>
                        <div class="roadmap-start" x-text="'Начать не позднее '+date(item.latest_start,true)"></div>
                    </div>
                </article>
            </template>
        </div>
    </div>
    <div class="empty-state" x-show="!roadmap.data.length"><span x-html="icon('Map')"></span>
        <h3>На этот год работы не запланированы</h3>
    </div>
    <div class="pagination"><span x-text="roadmap.total+' работ'"></span>
        <div><button class="icon-button" aria-label="Предыдущая страница плана" :disabled="roadmapPage <= 1 || busy"
                @click="roadmapPage--; run(()=>loadRoadmap())" x-html="icon('ChevronLeft')"></button><span
                x-text="roadmapPage+' / '+roadmap.last_page"></span><button class="icon-button"
                aria-label="Следующая страница плана" :disabled="roadmapPage >= roadmap.last_page || busy"
                @click="roadmapPage++; run(()=>loadRoadmap())" x-html="icon('ChevronRight')"></button></div>
    </div>
    <div class="report-note"><span x-html="icon('CalendarDays')"></span>Базовый план: Пн–Пт, 8 ч в день. Резерв дорожной
        карты не включает текущие задачи, праздники и отпуска.</div>
</section>
