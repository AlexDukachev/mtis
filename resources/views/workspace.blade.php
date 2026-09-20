@extends('layout')
@section('content')
    <div x-data="workspace" class="app-shell" @keydown.escape.window="modal ? closeModal() : mobileNav = false">
        <div class="mobile-shade" x-show="mobileNav" x-cloak @click="mobileNav = false"></div>
        <aside class="sidebar" :class="{ 'mobile-open': mobileNav }">
            <a href="#dashboard" class="brand" @click.prevent="go('dashboard')"><span
                    class="brand-mark">m<span>t</span></span><strong>MTIS</strong><span
                    class="workspace-tag">workspace</span></a>
            <div class="nav-caption">РАБОЧЕЕ ПРОСТРАНСТВО</div>
            <nav aria-label="Основная навигация">
                <template x-for="item in nav.filter(n => !n.manager || isManager)" :key="item.id">
                    <a :href="'#' + item.id" @click.prevent="go(item.id)" :class="{ active: view === item.id }"><span
                            x-html="icon(item.icon)"></span><span x-text="item.title"></span><span class="nav-count"
                            x-show="item.id === 'qa' && dashboard.statuses.qa" x-text="dashboard.statuses.qa"></span></a>
                </template>
            </nav>
            <div class="sidebar-bottom">
                <a href="#notifications" @click.prevent="go('notifications')"
                    :class="{ active: view === 'notifications' }"><span x-html="icon('Bell')"></span>Уведомления<span
                        class="nav-count" x-show="unread" x-text="unread"></span></a>
                <a href="#admin" x-show="user.role === 'admin'" @click.prevent="go('admin')"
                    :class="{ active: view === 'admin' }"><span x-html="icon('Settings')"></span>Администрирование</a>
                <div class="account"><button class="avatar" title="Мой профиль" aria-label="Мой профиль"
                        @click="openProfile()" :style="avatarStyle(user.id)" x-text="initials(user.name)"></button>
                    <button class="account-name" @click="openProfile()"><strong x-text="user.name"></strong><small
                            x-text="roles[user.role]"></small></button>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="icon-button" title="Выйти"
                            aria-label="Выйти" x-html="icon('LogOut')"></button></form>
                </div>
            </div>
        </aside>
        <div class="main-shell">
            <header class="topbar">
                <button class="icon-button mobile-only" title="Открыть меню" aria-label="Открыть меню"
                    @click="mobileNav = !mobileNav" x-html="icon('Menu')"></button>
                <div class="breadcrumb">Рабочее пространство <span x-html="icon('ChevronRight')"></span><strong
                        x-text="title"></strong></div>
                <form class="global-search" @submit.prevent="go('issues', { q: $refs.globalSearch.value })"><span
                        x-html="icon('Search')"></span><input x-ref="globalSearch" aria-label="Поиск по задачам"
                        placeholder="Найти задачу..."></form>
                <button class="icon-button top-notification" title="Уведомления" aria-label="Уведомления"
                    @click="go('notifications')"><span x-html="icon('Bell')"></span><i x-show="unread"></i></button>
                <button class="icon-button" :title="dark ? 'Светлая тема' : 'Темная тема'"
                    :aria-label="dark ? 'Светлая тема' : 'Темная тема'" @click="toggleTheme()"
                    x-html="icon(dark ? 'Sun' : 'Moon')"></button>
                <button class="avatar small" title="Открыть профиль" aria-label="Открыть профиль" @click="openProfile()"
                    :style="avatarStyle(user.id)" x-text="initials(user.name)"></button>
            </header>
            <main class="content" :aria-busy="busy">
                <div class="loading-screen" x-show="!ready && !error"><span class="spinner"></span>Загрузка рабочего
                    пространства</div>
                <div x-show="ready" x-cloak>
                    <div class="page-heading">
                        <div>
                            <div class="section-eyebrow"
                                x-text="isManager ? 'УПРАВЛЕНИЕ КОМАНДОЙ' : 'РАБОЧЕЕ ПРОСТРАНСТВО'"></div>
                            <h1 x-text="title"></h1>
                        </div>
                        <div class="actions"><button class="button" x-show="['workload', 'reports'].includes(view)"
                                @click="exportReport()"><span
                                    x-html="icon('ArrowDownToLine')"></span>Экспорт</button><button class="button primary"
                                x-show="permissions.includes('create')" @click="newIssue()"><span
                                    x-html="icon('Plus')"></span>Создать задачу</button></div>
                    </div>
                    @include('partials.workload')
                    @include('partials.issues')
                    @include('partials.overview')
                    @include('partials.admin')
                    @include('partials.roadmap')
                </div>
                <div class="alert danger page-error" x-show="error && !modal" x-cloak role="alert"><span
                        x-html="icon('CircleAlert')"></span><span x-text="error"></span><button class="icon-button"
                        title="Закрыть" aria-label="Закрыть ошибку" @click="error = ''" x-html="icon('X')"></button>
                </div>
            </main>
            <footer class="app-footer"><span><i class="online-dot"></i>MTIS <span class="muted">/ Рабочее
                        пространство</span></span><span
                    x-text="'Обновлено ' + new Intl.DateTimeFormat('ru-RU', {hour:'2-digit',minute:'2-digit'}).format(new Date())"></span>
            </footer>
        </div>
        @include('partials.modals')
        <div class="toast" x-show="toast && !modal" x-cloak role="status"><span
                x-html="icon('CircleCheck')"></span><span x-text="toast"></span></div>
    </div>
@endsection
