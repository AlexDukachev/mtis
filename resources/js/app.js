import Alpine from "alpinejs";
import focus from "@alpinejs/focus";
import {
    createElement,
    LayoutDashboard,
    CircleCheck,
    ListTodo,
    FolderKanban,
    Columns3,
    Users,
    ChartNoAxesCombined,
    ChartColumn,
    Bell,
    Settings,
    Search,
    Plus,
    ChevronDown,
    ChevronRight,
    ChevronLeft,
    ArrowUpRight,
    ArrowLeft,
    ArrowRight,
    ArrowDownToLine,
    SlidersHorizontal,
    CalendarDays,
    Clock3,
    CircleAlert,
    TriangleAlert,
    Circle,
    Check,
    X,
    LogOut,
    Menu,
    Paperclip,
    MessageSquare,
    Send,
    Play,
    RotateCcw,
    LockKeyhole,
    MoreHorizontal,
    UserPlus,
    Save,
    Trash2,
    RefreshCw,
    Filter,
    FileText,
    Activity,
    ExternalLink,
    Sun,
    Moon,
    UserRound,
    Map,
} from "lucide";

const icons = {
    Sun,
    Moon,
    UserRound,
    Map,
    LayoutDashboard,
    CircleCheck,
    ListTodo,
    FolderKanban,
    Columns3,
    Users,
    ChartNoAxesCombined,
    ChartColumn,
    Bell,
    Settings,
    Search,
    Plus,
    ChevronDown,
    ChevronRight,
    ChevronLeft,
    ArrowUpRight,
    ArrowLeft,
    ArrowRight,
    ArrowDownToLine,
    SlidersHorizontal,
    CalendarDays,
    Clock3,
    CircleAlert,
    TriangleAlert,
    Circle,
    Check,
    X,
    LogOut,
    Menu,
    Paperclip,
    MessageSquare,
    Send,
    Play,
    RotateCcw,
    LockKeyhole,
    MoreHorizontal,
    UserPlus,
    Save,
    Trash2,
    RefreshCw,
    Filter,
    FileText,
    Activity,
    ExternalLink,
};
const icon = (name) =>
    createElement(icons[name] || Circle, {
        width: 18,
        height: 18,
        "stroke-width": 1.7,
        "aria-hidden": "true",
    }).outerHTML;
Alpine.plugin(focus);
Alpine.data("workspace", () => ({
    icon,
    ready: false,
    busy: false,
    error: "",
    toast: "",
    mobileNav: false,
    view: "dashboard",
    user: {},
    projects: [],
    users: [],
    teams: [],
    statuses: {},
    types: {},
    priorities: {},
    roles: {},
    permissions: [],
    savedFilters: [],
    unread: 0,
    dashboard: { statuses: {}, events: [], projects: [] },
    workload: { rows: [], hours: 0, capacity: 0 },
    issues: [],
    pagination: {},
    notifications: [],
    adminData: {
        users: [],
        teams: [],
        events: [],
        calendar: [],
        thresholds: {},
        permissions: {},
    },
    filters: {},
    page: 1,
    perPage: 25,
    theme: "system",
    dark: false,
    profileUser: null,
    profileReturn: "",
    teamSearch: "",
    rosterTeam: "",
    rosterPage: 1,
    roadmap: { data: [], total: 0, at_risk: 0, last_page: 1 },
    roadmapYear: new Date().getFullYear(),
    roadmapProject: "",
    roadmapArchived: false,
    roadmapPage: 1,
    roadmapRequestId: 0,
    roadmapStatuses: {
        planned: "Запланировано",
        in_progress: "В работе",
        done: "Завершено",
        cancelled: "Отменено",
    },
    months: [
        "Янв",
        "Фев",
        "Мар",
        "Апр",
        "Май",
        "Июн",
        "Июл",
        "Авг",
        "Сен",
        "Окт",
        "Ноя",
        "Дек",
    ],
    startEstimate: "",
    period: "week",
    teamId: "",
    from: "",
    to: "",
    loadFilter: "all",
    employee: null,
    modal: "",
    selected: null,
    detailTab: "activity",
    transitionStatus: "",
    reason: "",
    testResult: {},
    commentBody: "",
    replyTo: null,
    issueForm: {},
    issueEditing: false,
    form: {},
    adminTab: "users",
    filterName: "",
    showFilters: false,
    showColumns: false,
    columns: {
        project: true,
        status: true,
        assignee: true,
        priority: true,
        due: true,
        estimate: true,
    },
    worklog: { hours: 1, date: "", comment: "", remaining: 0 },
    dragged: null,
    requestId: 0,
    previousFocus: null,
    nav: [
        { id: "dashboard", title: "Обзор", icon: "LayoutDashboard" },
        { id: "mine", title: "Мои задачи", icon: "CircleCheck" },
        { id: "issues", title: "Все задачи", icon: "ListTodo" },
        { id: "projects", title: "Проекты", icon: "FolderKanban" },
        { id: "kanban", title: "Доска задач", icon: "Columns3" },
        { id: "roadmap", title: "Дорожная карта", icon: "Map" },
        { id: "qa", title: "Тестирование", icon: "CircleCheck" },
        { id: "team", title: "Команда", icon: "Users" },
        {
            id: "workload",
            title: "Загрузка команды",
            icon: "ChartNoAxesCombined",
            manager: true,
        },
        { id: "reports", title: "Отчеты", icon: "ChartColumn", manager: true },
    ],
    async init() {
        this.applyTheme(localStorage.getItem("mtis.theme") || "system");
        window
            .matchMedia("(prefers-color-scheme: dark)")
            .addEventListener("change", () => {
                if (this.theme === "system") this.applyTheme("system");
            });
        try {
            const saved = localStorage.getItem("mtis.columns");
            if (saved) this.columns = { ...this.columns, ...JSON.parse(saved) };
        } catch {}
        await this.run(async () => {
            await this.bootstrap();
            this.view =
                this.validView(location.hash.slice(1)) ||
                (this.isManager
                    ? "workload"
                    : this.user.role === "tester"
                      ? "qa"
                      : "mine");
            await this.loadView();
            this.ready = true;
        });
        window.addEventListener("hashchange", () => {
            const view = this.validView(location.hash.slice(1));
            if (view && view !== this.view) this.go(view);
        });
        this.$watch("columns", (value) =>
            localStorage.setItem("mtis.columns", JSON.stringify(value)),
        );
    },
    get isManager() {
        return ["admin", "manager"].includes(this.user.role);
    },
    applyTheme(theme) {
        this.theme = theme;
        this.dark =
            theme === "dark" ||
            (theme === "system" &&
                window.matchMedia("(prefers-color-scheme: dark)").matches);
        document.documentElement.dataset.theme = this.dark ? "dark" : "light";
        localStorage.setItem("mtis.theme", theme);
    },
    async toggleTheme() {
        const theme = this.dark ? "light" : "dark";
        this.applyTheme(theme);
        await this.run(async () => {
            this.user = await this.api("/profile", "PATCH", { theme });
        });
    },
    openProfile(person = this.user) {
        if (!person?.id) return;
        this.profileReturn = this.modal === "issue" ? "issue" : "";
        this.profileUser = this.users.find((u) => u.id === person.id) || person;
        this.form = {
            name: this.profileUser.name,
            email: this.profileUser.email,
            theme: this.user.theme || "system",
            current_password: "",
            password: "",
            password_confirmation: "",
        };
        this.openModal("profile");
    },
    async saveProfile() {
        await this.run(async () => {
            this.user = await this.api("/profile", "PATCH", this.form);
            this.applyTheme(this.user.theme);
            await this.bootstrap();
            this.modal = this.profileReturn;
            this.notify("Профиль сохранен");
        });
    },
    get roster() {
        return this.users.filter(
            (u) =>
                (!this.rosterTeam ||
                    u.teams.some((t) => t.id == this.rosterTeam)) &&
                `${u.name} ${u.position || ""}`
                    .toLocaleLowerCase("ru")
                    .includes(this.teamSearch.toLocaleLowerCase("ru")),
        );
    },
    get selectedTeam() {
        return this.teams.find((t) => t.id == this.rosterTeam);
    },
    editTeamMembers() {
        this.form = {
            ...this.selectedTeam,
            member_ids: this.users
                .filter((u) => u.teams.some((t) => t.id == this.rosterTeam))
                .map((u) => u.id),
        };
        this.openModal("teamMembers");
    },
    async saveTeamMembers() {
        await this.run(async () => {
            await this.api("/teams/" + this.form.id, "PUT", {
                name: this.form.name,
                member_ids: this.form.member_ids,
            });
            await this.bootstrap();
            this.closeModal();
            this.notify("Команда обновлена");
        });
    },
    actionName(status) {
        return (
            {
                review: "Рассмотреть",
                ready: "В очередь разработки",
                development: "Начать работу",
                qa: "Передать на проверку",
                testing: "Взять на проверку",
                returned: "Вернуть на доработку",
                acceptance: "Проверка пройдена",
                closed: "Принять и закрыть",
                blocked: "Заблокировать",
                clarification: "Запросить уточнение",
                deferred: "Отложить",
                cancelled: "Отменить задачу",
                new: "Переоткрыть",
            }[status] || this.statuses[status]
        );
    },
    get primaryTransitions() {
        return (this.selected?.allowed_transitions || []).filter((s) =>
            [
                "ready",
                "development",
                "qa",
                "testing",
                "returned",
                "acceptance",
                "closed",
            ].includes(s),
        );
    },
    get extraTransitions() {
        return (this.selected?.allowed_transitions || []).filter(
            (s) => !this.primaryTransitions.includes(s),
        );
    },
    async beginAssign(issue = this.selected) {
        if (!issue.comments) {
            await this.openIssue(issue.id);
            if (this.error) return;
        }
        this.form = {
            assignee_id: this.selected.assignee_id || "",
            tester_id: this.selected.tester_id || "",
        };
        this.openModal("assign");
    },
    async saveAssignment() {
        await this.run(async () => {
            this.selected = (
                await this.api("/issues/" + this.selected.id, "PATCH", {
                    assignee_id: this.form.assignee_id
                        ? Number(this.form.assignee_id)
                        : null,
                    tester_id: this.form.tester_id
                        ? Number(this.form.tester_id)
                        : null,
                })
            ).data;
            this.modal = "issue";
            await this.loadView();
            this.notify("Исполнители назначены");
        });
    },
    beginSchedule() {
        this.form = {
            planned_start: this.selected.planned_start?.slice(0, 10) || "",
            planned_end: this.selected.planned_end?.slice(0, 10) || "",
        };
        this.openModal("schedule");
    },
    async saveSchedule() {
        await this.run(async () => {
            this.selected = (
                await this.api(
                    "/issues/" + this.selected.id,
                    "PATCH",
                    this.form,
                )
            ).data;
            this.modal = "issue";
            await this.loadView();
            this.notify("Период работ сохранен");
        });
    },
    async loadRoadmap() {
        const requestId = ++this.roadmapRequestId;
        const result = await this.api(
            "/roadmap?" +
                new URLSearchParams({
                    year: this.roadmapYear,
                    page: this.roadmapPage,
                    include_completed: this.roadmapArchived ? "1" : "0",
                    ...(this.roadmapProject
                        ? { project_id: this.roadmapProject }
                        : {}),
                }),
        );
        if (requestId === this.roadmapRequestId) this.roadmap = result;
    },
    editRoadmap(item = null) {
        this.form = item
            ? {
                  ...item,
                  deadline: item.deadline.slice(0, 10),
                  developer_ids: item.developers.map((u) => u.id),
              }
            : {
                  title: "",
                  description: "",
                  project_id:
                      this.projects.find(
                          (p) =>
                              this.user.role === "admin" ||
                              this.teams.some(
                                  (t) =>
                                      t.id === p.team_id &&
                                      t.leader_id === this.user.id,
                              ),
                      )?.id || "",
                  deadline: `${this.roadmapYear}-12-31`,
                  duration_days: 60,
                  required_people: 3,
                  status: "planned",
                  developer_ids: [],
              };
        this.openModal("roadmap");
    },
    async saveRoadmap() {
        await this.run(async () => {
            await this.api(
                "/roadmap" + (this.form.id ? "/" + this.form.id : ""),
                this.form.id ? "PUT" : "POST",
                this.form,
            );
            this.closeModal();
            this.roadmapPage = 1;
            await this.loadRoadmap();
            this.notify("Дорожная карта обновлена");
        });
    },
    roadmapBar(item) {
        const start = new Date(`${this.roadmapYear}-01-01T00:00:00Z`).getTime(),
            end = new Date(
                `${Number(this.roadmapYear) + 1}-01-01T00:00:00Z`,
            ).getTime();
        const left = Math.max(
                start,
                new Date(item.latest_start + "T00:00:00Z").getTime(),
            ),
            right = Math.min(
                end,
                new Date(item.deadline.slice(0, 10) + "T00:00:00Z").getTime() +
                    86400000,
            );
        return `left:${((left - start) / (end - start)) * 100}%;width:${Math.max(0.4, ((right - left) / (end - start)) * 100)}%`;
    },
    get title() {
        return (
            this.nav.find((n) => n.id === this.view)?.title ||
            { notifications: "Уведомления", admin: "Администрирование" }[
                this.view
            ] ||
            "MTIS"
        );
    },
    get filteredRows() {
        return this.workload.rows.filter(
            (row) => this.loadFilter === "all" || row.state === this.loadFilter,
        );
    },
    toggleEmployee(row) {
        this.employee = this.employee?.user.id === row.user.id ? null : row;
        if (!this.employee) return;
        this.$nextTick(() => {
            if (this.employee?.user.id !== row.user.id) return;
            document
                .getElementById("employee-tasks-" + row.user.id)
                ?.scrollIntoView({
                    block: "nearest",
                    inline: "start",
                    behavior: "smooth",
                });
        });
    },
    get totalPercent() {
        return this.workload.incomplete || !this.workload.capacity
            ? null
            : Math.round((this.workload.hours / this.workload.capacity) * 100);
    },
    get activeFilters() {
        return Object.values(this.filters).filter(Boolean).length;
    },
    get memberOptions() {
        return (
            this.projects.find((p) => p.id == this.issueForm.project_id)
                ?.members || []
        );
    },
    get activity() {
        if (!this.selected) return [];
        return [
            ...(this.selected.events || []).map((e) => ({
                ...e,
                kind: "event",
            })),
            ...(this.selected.comments || []).map((e) => ({
                ...e,
                kind: "comment",
            })),
        ].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    },
    validView(view) {
        if (["workload", "reports"].includes(view) && !this.isManager)
            return null;
        if (view === "admin" && this.user.role !== "admin") return null;
        return [
            ...this.nav.map((n) => n.id),
            "notifications",
            "admin",
        ].includes(view)
            ? view
            : null;
    },
    initials(name) {
        return (name || "")
            .split(" ")
            .slice(0, 2)
            .map((p) => p[0])
            .join("");
    },
    avatarStyle(id) {
        const colors = [
            "#e4eee8",
            "#e6eaf5",
            "#f4e8db",
            "#ede6f3",
            "#e5eef0",
            "#f6e4e6",
        ];
        return "background:" + colors[(id || 0) % colors.length];
    },
    date(value, year = false) {
        if (!value) return "Не задан";
        return new Intl.DateTimeFormat("ru-RU", {
            day: "numeric",
            month: "short",
            ...(year ? { year: "numeric" } : {}),
        }).format(new Date(value.slice(0, 10) + "T12:00:00"));
    },
    time(value) {
        return new Intl.DateTimeFormat("ru-RU", {
            day: "numeric",
            month: "short",
            hour: "2-digit",
            minute: "2-digit",
            timeZone: "Asia/Almaty",
        }).format(new Date(value));
    },
    hours(value) {
        return value === null || value === undefined
            ? "Без оценки"
            : Number(value).toLocaleString("ru-RU") + " ч";
    },
    stateName(state) {
        return (
            {
                normal: "Плановая загрузка",
                reserve: "Есть резерв",
                overload: "Перегрузка",
                incomplete: "Неполные данные",
                unavailable: "Нет емкости",
            }[state] || state
        );
    },
    eventName(action) {
        return (
            {
                created: "создал(а) задачу",
                updated: "изменил(а) задачу",
                status_changed: "изменил(а) статус",
                commented: "добавил(а) комментарий",
                work_logged: "добавил(а) трудозатраты",
                file_uploaded: "прикрепил(а) файл",
                overdue: "срок задачи истек",
                due_soon: "приближается срок",
                user_saved: "изменил(а) пользователя",
                settings_updated: "изменил(а) настройки",
                team_created: "создал(а) команду",
                project_saved: "изменил(а) проект",
                login: "вошел(ла) в систему",
                calendar_updated: "изменил(а) календарь",
                workflow_updated: "изменил(а) workflow",
            }[action] || action
        );
    },
    fieldName(key) {
        return (
            {
                title: "Название",
                description: "Описание",
                assignee_id: "Исполнитель",
                tester_id: "Тестировщик",
                due_date: "Срок",
                estimate: "Первоначальная оценка",
                remaining: "Остаточная оценка",
                priority: "Приоритет",
                planned_start: "Начало работ",
                planned_end: "Окончание работ",
                status: "Статус",
            }[key] || key
        );
    },
    changeValue(key, value) {
        if (key === "status") return this.statuses[value];
        if (key === "priority") return this.priorities[value];
        if (key.endsWith("_id"))
            return (
                this.users.find((u) => u.id == value)?.name ||
                value ||
                "Не назначен"
            );
        return value ?? "Не задано";
    },
    async api(path, method = "GET", body) {
        const headers = {
            Accept: "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                .content,
        };
        if (body && !(body instanceof FormData))
            headers["Content-Type"] = "application/json";
        const res = await fetch("/api/v1" + path, {
            method,
            headers,
            credentials: "same-origin",
            ...(body
                ? {
                      body:
                          body instanceof FormData
                              ? body
                              : JSON.stringify(body),
                  }
                : {}),
        });
        const data = await res.json().catch(() => ({}));
        if (res.headers.get("X-CSRF-Token"))
            document.querySelector('meta[name="csrf-token"]').content =
                res.headers.get("X-CSRF-Token");
        if (res.status === 401 || res.status === 419) {
            location.href = "/login";
            throw new Error("Необходимо войти снова.");
        }
        if (!res.ok)
            throw new Error(
                data.errors
                    ? Object.values(data.errors).flat().join(" ")
                    : data.message || "Не удалось выполнить запрос.",
            );
        return data;
    },
    async run(action) {
        this.error = "";
        this.busy = true;
        try {
            await action();
        } catch (e) {
            this.error = e.message;
        } finally {
            this.busy = false;
        }
    },
    notify(text) {
        this.toast = text;
        setTimeout(() => (this.toast = ""), 4000);
    },
    async bootstrap() {
        const data = await this.api("/bootstrap");
        Object.assign(this, {
            user: data.user,
            projects: data.projects,
            users: data.users,
            teams: data.teams,
            statuses: data.statuses,
            types: data.types,
            priorities: data.priorities,
            roles: data.roles,
            permissions: data.permissions,
            savedFilters: data.filters,
            unread: data.unread,
        });
        this.applyTheme(data.user.theme || "system");
    },
    async go(view, filters = null) {
        this.view = view;
        this.mobileNav = false;
        this.employee = null;
        this.page = 1;
        this.filters = filters || {};
        this.loadFilter = "all";
        history.replaceState(null, "", "#" + view);
        await this.run(() => this.loadView());
    },
    async loadView() {
        if (this.view === "roadmap") await this.loadRoadmap();
        if (
            ["dashboard", "reports", "workload", "projects"].includes(this.view)
        )
            this.dashboard = await this.api("/reports/dashboard");
        if (
            ["workload", "reports"].includes(this.view) ||
            (this.view === "dashboard" && this.isManager)
        )
            await this.loadWorkload();
        if (["issues", "mine", "kanban", "qa"].includes(this.view))
            await this.loadIssues();
        if (this.view === "notifications") {
            const result = await this.api("/notifications?page=" + this.page);
            this.notifications = result.data;
            this.pagination = result;
        }
        if (this.view === "admin") this.adminData = await this.api("/admin");
    },
    async loadIssues() {
        const id = ++this.requestId;
        const params = new URLSearchParams(
            Object.entries({
                ...this.filters,
                ...(this.view === "mine" ? { mine: "1" } : {}),
                ...(this.view === "qa" ? { qa: "1" } : {}),
                page: this.page,
                per_page: this.perPage,
            }).filter(([, v]) => v !== "" && v != null),
        );
        const result = await this.api("/issues?" + params);
        if (id !== this.requestId) return;
        this.issues = result.data;
        this.pagination = result.meta;
    },
    async filterIssues() {
        this.page = 1;
        await this.run(() => this.loadIssues());
    },
    async loadWorkload() {
        if (this.period === "custom" && (!this.from || !this.to)) return;
        const params = new URLSearchParams(
            Object.entries({
                period: this.period,
                team_id: this.teamId,
                from: this.from,
                to: this.to,
            }).filter(([, v]) => v),
        );
        this.workload = await this.api("/reports/workload?" + params);
    },
    async refreshWorkload() {
        await this.run(() => this.loadWorkload());
    },
    openModal(name) {
        this.previousFocus = document.activeElement;
        this.modal = name;
        this.error = "";
    },
    closeModal() {
        this.modal = "";
        this.selected = null;
        this.error = "";
        this.previousFocus?.focus?.();
    },
    async openIssue(id) {
        await this.run(async () => {
            this.selected = (await this.api("/issues/" + id)).data;
            this.detailTab = "activity";
            this.commentBody = "";
            this.replyTo = null;
            this.openModal("issue");
        });
    },
    newIssue(parent = null) {
        this.issueEditing = false;
        this.issueForm = {
            title: "",
            description: "",
            project_id: parent?.project_id || this.projects[0]?.id || "",
            type: "task",
            priority: "normal",
            assignee_id: "",
            tester_id: "",
            due_date: "",
            planned_start: "",
            planned_end: "",
            estimate: "",
            remaining: "",
            parent_id: parent?.id || null,
        };
        this.openModal("create");
    },
    editIssue() {
        this.issueEditing = true;
        this.issueForm = {
            ...this.selected,
            due_date: this.selected.due_date?.slice(0, 10) || "",
            planned_start: this.selected.planned_start?.slice(0, 10) || "",
            planned_end: this.selected.planned_end?.slice(0, 10) || "",
        };
        this.openModal("create");
    },
    async saveIssue() {
        await this.run(async () => {
            const source = this.issueForm;
            const keys = [
                "title",
                "description",
                "type",
                "priority",
                "due_date",
            ];
            const data = Object.fromEntries(
                keys
                    .filter((k) => k in source)
                    .map((k) => [k, source[k] === "" ? null : source[k]]),
            );
            if (!this.issueEditing) {
                data.project_id = Number(source.project_id);
                if (source.parent_id) data.parent_id = source.parent_id;
            }
            if (
                !this.issueEditing &&
                (this.isManager || this.permissions.includes("assign"))
            ) {
                data.assignee_id = source.assignee_id
                    ? Number(source.assignee_id)
                    : null;
                data.tester_id = source.tester_id
                    ? Number(source.tester_id)
                    : null;
            }
            const result = await this.api(
                "/issues" + (this.issueEditing ? "/" + source.id : ""),
                this.issueEditing ? "PATCH" : "POST",
                data,
            );
            this.selected = result.data;
            this.modal = "issue";
            this.detailTab = "activity";
            await this.loadView();
            this.notify(
                this.issueEditing ? "Задача обновлена" : "Задача создана",
            );
        });
    },
    beginEstimate() {
        this.form = {
            estimate: this.selected.estimate ?? "",
            remaining: this.selected.remaining ?? "",
            solution: this.selected.solution || "",
        };
        this.modal = "estimate";
    },
    async saveEstimate() {
        await this.run(async () => {
            this.selected = (
                await this.api(
                    "/issues/" + this.selected.id,
                    "PATCH",
                    this.form,
                )
            ).data;
            this.modal = "issue";
            await this.loadView();
            this.notify("Оценка сохранена");
        });
    },
    beginTransition(status) {
        this.transitionStatus = status;
        this.reason = "";
        this.testResult = {
            expected: "",
            actual: "",
            steps: "",
            environment: "",
        };
        this.startEstimate = "";
        if (
            status === "development" &&
            (this.selected.estimate === null ||
                this.selected.remaining === null)
        ) {
            this.modal = "start";
            return;
        }
        if (!["returned", "blocked", "closed", "cancelled"].includes(status)) {
            this.saveTransition();
            return;
        }
        this.modal = "transition";
    },
    async saveTransition() {
        await this.run(async () => {
            this.selected = (
                await this.api(
                    "/issues/" + this.selected.id + "/transitions",
                    "POST",
                    {
                        status: this.transitionStatus,
                        reason: this.reason,
                        test_result: this.testResult,
                        ...(this.startEstimate !== ""
                            ? { estimate: Number(this.startEstimate) }
                            : {}),
                    },
                )
            ).data;
            this.modal = "issue";
            await this.loadView();
            this.notify("Статус изменен");
        });
    },
    async dropIssue(status) {
        const issue = this.issues.find((i) => i.id === this.dragged);
        this.dragged = null;
        if (!issue || issue.status === status) return;
        if (!issue.allowed_transitions.includes(status)) {
            this.error =
                "Этот переход недоступен для вашей роли и текущего статуса.";
            return;
        }
        await this.openIssue(issue.id);
        this.beginTransition(status);
    },
    async sendComment() {
        await this.run(async () => {
            await this.api(
                "/issues/" + this.selected.id + "/comments",
                "POST",
                { body: this.commentBody, parent_id: this.replyTo },
            );
            this.commentBody = "";
            this.replyTo = null;
            this.selected = (
                await this.api("/issues/" + this.selected.id)
            ).data;
        });
    },
    async uploadFile(event) {
        const file = event.target.files[0];
        if (!file) return;
        await this.run(async () => {
            const body = new FormData();
            body.append("file", file);
            await this.api(
                "/issues/" + this.selected.id + "/attachments",
                "POST",
                body,
            );
            this.selected = (
                await this.api("/issues/" + this.selected.id)
            ).data;
            this.notify("Файл прикреплен");
        });
        event.target.value = "";
    },
    beginWorklog() {
        this.worklog = {
            hours: 1,
            date: new Intl.DateTimeFormat("en-CA", {
                timeZone: "Asia/Almaty",
            }).format(new Date()),
            comment: "",
            remaining: this.selected.remaining ?? 0,
        };
        this.modal = "worklog";
    },
    async saveWorklog() {
        await this.run(async () => {
            await this.api(
                "/issues/" + this.selected.id + "/worklogs",
                "POST",
                this.worklog,
            );
            this.selected = (
                await this.api("/issues/" + this.selected.id)
            ).data;
            this.modal = "issue";
            await this.loadView();
            this.notify("Трудозатраты сохранены");
        });
    },
    async saveFilter() {
        await this.run(async () => {
            const filter = await this.api("/filters", "POST", {
                name: this.filterName,
                filters: this.filters,
            });
            this.savedFilters.push(filter);
            this.modal = "";
            this.notify("Фильтр сохранен");
        });
    },
    async deleteFilter(id) {
        await this.run(async () => {
            await this.api("/filters/" + id, "DELETE");
            this.savedFilters = this.savedFilters.filter((f) => f.id !== id);
        });
    },
    async readAll() {
        await this.run(async () => {
            await this.api("/notifications/read", "POST", {});
            this.unread = 0;
            await this.loadView();
        });
    },
    async readNotification(n) {
        await this.api("/notifications/read", "POST", { id: n.id });
        if (!n.read_at) this.unread = Math.max(0, this.unread - 1);
        n.read_at = new Date().toISOString();
        if (n.issue_id) await this.openIssue(n.issue_id);
    },
    openUser(user = null) {
        this.form = user
            ? {
                  ...user,
                  password: "",
                  team_ids: user.teams.map((t) => t.id),
                  project_ids: user.projects.map((p) => p.id),
              }
            : {
                  name: "",
                  username: "",
                  email: "",
                  password: "",
                  role: "developer",
                  active: true,
                  department: "",
                  position: "",
                  weekly_capacity: 40,
                  wip_limit: 3,
                  work_days: [1, 2, 3, 4, 5],
                  team_ids: [],
                  project_ids: [],
              };
        this.openModal("user");
    },
    async saveUser() {
        await this.run(async () => {
            await this.api(
                "/users" + (this.form.id ? "/" + this.form.id : ""),
                this.form.id ? "PUT" : "POST",
                this.form,
            );
            this.modal = "";
            await this.bootstrap();
            await this.loadView();
            this.notify("Пользователь сохранен");
        });
    },
    openProject(project = null) {
        this.form = project
            ? { ...project, member_ids: project.members.map((m) => m.id) }
            : {
                  name: "",
                  key: "",
                  description: "",
                  team_id: this.teams[0]?.id || "",
                  owner_id: this.user.id,
                  member_ids: [this.user.id],
                  status: "active",
                  color: "#16866a",
              };
        this.openModal("project");
    },
    async saveProject() {
        await this.run(async () => {
            await this.api(
                "/projects" + (this.form.id ? "/" + this.form.id : ""),
                this.form.id ? "PUT" : "POST",
                this.form,
            );
            this.modal = "";
            await this.bootstrap();
            await this.loadView();
            this.notify("Проект сохранен");
        });
    },
    async saveTeam() {
        await this.run(async () => {
            await this.api("/teams", "POST", this.form);
            this.modal = "";
            await this.bootstrap();
            await this.loadView();
            this.notify("Команда создана");
        });
    },
    async saveSettings() {
        await this.run(async () => {
            await this.api("/settings", "PUT", {
                thresholds: this.adminData.thresholds,
                permissions: this.adminData.permissions,
            });
            this.notify("Настройки сохранены");
        });
    },
    async saveCalendar() {
        await this.run(async () => {
            await this.api("/calendar", "POST", {
                ...this.form,
                user_id: this.form.user_id || null,
            });
            this.modal = "";
            await this.loadView();
            this.notify("Календарь обновлен");
        });
    },
    async savePreferences() {
        await this.run(async () => {
            await this.api("/notifications/preferences", "PUT", {
                preferences: this.form,
            });
            this.user.notification_preferences = { ...this.form };
            this.modal = "";
            this.notify("Настройки уведомлений сохранены");
        });
    },
    exportReport() {
        const rows = [
            [
                "Сотрудник",
                "Осталось, ч",
                "Емкость, ч",
                "Загрузка, %",
                "WIP",
                "Без оценки",
                "Без плана",
                "Просрочено",
                "Заблокировано",
            ],
            ...this.workload.rows.map((r) => [
                r.user.name,
                r.hours,
                r.capacity,
                r.percent ?? "Неполные данные",
                r.wip,
                r.unestimated,
                r.unplanned,
                r.overdue,
                r.blocked,
            ]),
        ];
        const csv = rows
            .map((row) =>
                row
                    .map(
                        (value) =>
                            '"' +
                            String(value)
                                .replaceAll('"', '""')
                                .replace(/^[=+\-@]/, "'$&") +
                            '"',
                    )
                    .join(";"),
            )
            .join("\r\n");
        const url = URL.createObjectURL(
            new Blob(["\uFEFF", csv], { type: "text/csv;charset=utf-8" }),
        );
        const link = document.createElement("a");
        link.href = url;
        link.download = "mtis-workload-" + this.workload.from + ".csv";
        link.click();
        URL.revokeObjectURL(url);
    },
}));
window.Alpine = Alpine;
Alpine.start();
