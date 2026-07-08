<template>
    <div class="crm-layout-root flex h-dvh max-h-dvh min-h-0 overflow-hidden bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
        <div
            v-if="showMobileAppGate"
            class="fixed inset-0 z-[70] flex min-h-dvh items-center justify-center bg-zinc-950 px-4 py-6 text-zinc-50 lg:hidden"
        >
            <div class="w-full max-w-sm space-y-5 rounded-3xl border border-zinc-800 bg-zinc-900/95 p-6 shadow-2xl">
                <div class="space-y-3 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-zinc-900">
                        <Smartphone class="h-7 w-7" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold">Откройте кабинет через приложение</h1>
                        <p class="mt-2 text-sm text-zinc-400">
                            Мобильный браузер для CRM будет отключён. Установите PWA-приложение и работайте через него.
                        </p>
                    </div>
                </div>

                <div class="space-y-3 rounded-2xl border border-zinc-800 bg-zinc-950/60 p-4 text-sm text-zinc-300">
                    <div class="font-medium text-zinc-100">Что будет в приложении</div>
                    <div>Заказы, контрагенты, отчёты, счета и AI-чат в упрощённом мобильном интерфейсе.</div>
                </div>

                <div class="space-y-3">
                    <button
                        v-if="canInstallApp"
                        type="button"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-white px-4 py-3 text-sm font-medium text-zinc-900 transition hover:bg-zinc-200"
                        @click="triggerInstallPrompt"
                    >
                        <Download class="h-4 w-4" />
                        Установить приложение
                    </button>

                    <div v-else class="rounded-2xl border border-zinc-800 bg-zinc-950/60 p-4 text-sm text-zinc-300">
                        Установка доступна из меню браузера:
                        <span class="font-medium text-zinc-100">Добавить на главный экран</span>
                        или
                        <span class="font-medium text-zinc-100">Установить приложение</span>.
                    </div>

                    <button
                        type="button"
                        class="flex w-full items-center justify-center rounded-2xl border border-zinc-600 bg-zinc-800 px-4 py-3 text-sm font-medium text-zinc-100 transition hover:bg-zinc-700"
                        @click="continueMobileBrowserCabinet"
                    >
                        Продолжить в браузере
                    </button>

                    <a
                        href="/"
                        class="flex w-full items-center justify-center rounded-2xl border border-zinc-700 px-4 py-3 text-sm font-medium text-zinc-200 transition hover:bg-zinc-800"
                    >
                        Вернуться на сайт
                    </a>
                </div>
            </div>
        </div>

        <div
            v-else-if="showMobileAppShell"
            class="flex h-dvh max-h-dvh min-h-0 w-full flex-col overflow-hidden bg-zinc-50 dark:bg-zinc-950 lg:hidden"
        >
            <header class="shrink-0 border-b border-zinc-200 bg-zinc-50/95 px-4 py-3 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/95">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0 truncate text-sm text-zinc-600 dark:text-zinc-300">
                        {{ authUser?.name || 'Пользователь' }}
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="authUser?.mobile_nav?.candidate_keys?.length"
                            type="button"
                            class="flex h-10 w-10 items-center justify-center rounded-2xl border border-zinc-200 text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                            title="Нижнее меню приложения"
                            @click="openMobileNavModal"
                        >
                            <LayoutGrid class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="flex h-10 w-10 items-center justify-center rounded-2xl border border-zinc-200 text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                            title="Внешний вид"
                            @click="appearanceModalOpen = true"
                        >
                            <Palette class="h-4 w-4" />
                        </button>
                        <ThemeToggle />
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="flex h-10 w-10 items-center justify-center rounded-2xl border border-zinc-200 text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        >
                            <LogOut class="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            </header>

            <div
                v-if="mobileNavModalOpen"
                class="fixed inset-0 z-[60] flex items-end justify-center bg-zinc-950/60 px-3 py-4 lg:hidden"
                role="dialog"
                aria-modal="true"
                aria-label="Настройка нижнего меню"
                @click.self="mobileNavModalOpen = false"
            >
                <div class="w-full max-w-md rounded-3xl border border-zinc-200 bg-white p-4 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Кнопки внизу экрана</div>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        До 6 пунктов (выбрано {{ mobileNavDraftKeys.length }}/6). Пустой список после «Сбросить» — как задано для роли в администрировании.
                    </p>
                    <div
                        v-if="mobileNavPresets.length"
                        class="mt-3 flex flex-wrap gap-2"
                    >
                        <button
                            v-for="preset in mobileNavPresets"
                            :key="preset.id"
                            type="button"
                            class="rounded-xl border border-zinc-200 px-2.5 py-1.5 text-left text-xs transition hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800"
                            :title="preset.description"
                            @click="applyMobileNavPreset(preset)"
                        >
                            <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ preset.label }}</span>
                        </button>
                    </div>
                    <p
                        v-if="mobileNavDraftError"
                        class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100"
                    >
                        {{ mobileNavDraftError }}
                    </p>
                    <div class="mt-4 max-h-[45vh] space-y-2 overflow-y-auto">
                        <label
                            v-for="entry in mobileNavCandidateEntries"
                            :key="entry.key"
                            class="flex cursor-pointer items-center gap-3 rounded-2xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700"
                        >
                            <input
                                type="checkbox"
                                class="h-4 w-4 shrink-0 rounded border-zinc-300"
                                :checked="mobileNavDraftKeys.includes(entry.key)"
                                @change="toggleMobileNavDraftKey(entry.key)"
                            >
                            <span>{{ entry.label }}</span>
                        </label>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            :class="crmBtnCreate"
                            class="flex-1"
                            :disabled="mobileNavSaving"
                            @click="saveMobileNavDraft"
                        >
                            {{ mobileNavSaving ? 'Сохранение…' : 'Сохранить' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-2xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600"
                            :disabled="mobileNavSaving"
                            @click="resetMobileNavDraft"
                        >
                            Сбросить
                        </button>
                        <button
                            type="button"
                            class="rounded-2xl border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600"
                            @click="mobileNavModalOpen = false"
                        >
                            Отмена
                        </button>
                    </div>
                </div>
            </div>

            <main
                class="min-h-0 flex-1 bg-zinc-50 px-4 pt-1.5 pb-28 dark:bg-zinc-950"
                :class="mainFill ? 'flex h-0 flex-col overflow-hidden' : 'overflow-y-auto'"
                scroll-region
            >
                <div v-if="mainFill" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <slot />
                </div>
                <slot v-else />
            </main>

            <nav class="fixed bottom-0 left-0 right-0 z-50 border-t border-zinc-200 bg-white/95 px-2 py-2 pb-[calc(0.5rem+env(safe-area-inset-bottom))] backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/95">
                <div class="flex gap-1">
                    <button
                        v-for="item in mobileNavItems"
                        :key="item.key"
                        type="button"
                        class="relative flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-2xl px-1 py-2 text-[11px] font-medium transition-colors"
                        :class="isMobileNavItemActive(item.key)
                            ? 'bg-zinc-900 text-white dark:bg-zinc-50 dark:text-zinc-900'
                            : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                        @click="handleMenuSelect(item.key)"
                    >
                        <span class="relative inline-flex">
                            <component :is="item.icon" class="h-4 w-4" />
                            <span
                                v-if="menuBadgeFor(item.key) > 0"
                                class="absolute -right-1.5 -top-1 flex h-3.5 min-w-[14px] items-center justify-center rounded-full bg-rose-600 px-0.5 text-[8px] font-bold leading-none text-white"
                            >
                                {{ menuBadgeFor(item.key) > 99 ? '99+' : menuBadgeFor(item.key) }}
                            </span>
                        </span>
                        <span class="truncate">{{ item.label }}</span>
                    </button>
                </div>
            </nav>
        </div>

        <div
            v-if="!showMobileAppShell && mobileMenuOpen"
            class="fixed inset-0 z-40 bg-zinc-950/50 lg:hidden"
            @click="mobileMenuOpen = false"
        />

        <aside
            v-if="!showMobileAppShell"
            class="crm-layout-sidebar fixed inset-y-0 left-0 z-50 flex flex-col border-r border-zinc-200 bg-zinc-50 transition-all duration-300 dark:border-zinc-800 dark:bg-zinc-950"
            :class="[
                mobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                collapsed ? 'w-20' : 'w-64',
            ]"
        >
            <div class="flex h-14 items-center justify-between gap-2 border-b border-zinc-200 px-2 dark:border-zinc-800 sm:px-3">
                <div class="flex min-w-0 flex-1 items-center justify-start">
                    <div
                        v-if="!collapsed"
                        class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-zinc-900 dark:bg-white"
                    >
                        <img
                            :src="companyLogoSrc"
                            alt=""
                            class="h-8 w-8 object-contain"
                            width="32"
                            height="32"
                        >
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <button
                        v-if="!collapsed"
                        type="button"
                        class="crm-layout-icon-button flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 transition-colors hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
                        title="Внешний вид"
                        @click="appearanceModalOpen = true"
                    >
                        <Palette class="h-4 w-4" />
                    </button>
                    <ThemeToggle v-if="!collapsed" />

                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 transition-colors hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
                        :title="collapsed ? 'Развернуть меню' : 'Свернуть меню'"
                        @click="collapsed = !collapsed"
                    >
                        <PanelLeftClose v-if="!collapsed" class="h-4 w-4" />
                        <PanelLeftOpen v-else class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto p-2">
                <p
                    v-if="sidebarFavoriteError"
                    class="mb-2 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1.5 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    {{ sidebarFavoriteError }}
                </p>
                <div v-for="item in menuItems" :key="item.key" class="space-y-1">
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="crm-nav-link relative flex min-w-0 flex-1 items-center gap-3 px-3 py-2"
                            :class="activeKey === item.key ? 'crm-nav-link--active' : ''"
                            :title="collapsed ? item.label : false"
                            @click="handleMenuSelect(item.key, $event)"
                        >
                            <span class="relative inline-flex shrink-0">
                                <component :is="item.icon" class="h-5 w-5" />
                                <span
                                    v-if="menuBadgeFor(item.key) > 0"
                                    class="absolute -right-1.5 -top-1.5 flex h-[15px] min-w-[15px] items-center justify-center rounded-full bg-rose-600 px-0.5 text-[9px] font-bold leading-none text-white"
                                >
                                    {{ menuBadgeFor(item.key) > 99 ? '99+' : menuBadgeFor(item.key) }}
                                </span>
                            </span>
                            <span v-if="!collapsed" class="truncate text-sm font-medium">{{ item.label }}</span>
                        </button>

                        <button
                            v-if="!collapsed && item.children?.length"
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                            @click="toggleMenuGroup(item.key)"
                        >
                            <ChevronDown class="h-4 w-4 transition-transform" :class="isMenuGroupOpen(item.key) ? 'rotate-180' : ''" />
                        </button>

                        <button
                            v-if="!collapsed && canPinMenuKey(item.key)"
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-amber-500 dark:text-zinc-500 dark:hover:bg-zinc-800 dark:hover:text-amber-400"
                            :title="isSidebarFavoritePinned(item.key) ? 'Убрать из избранного' : 'Закрепить в избранном'"
                            :disabled="sidebarFavoriteSaving"
                            @click.stop="toggleSidebarFavorite(item.key)"
                        >
                            <Star
                                class="h-3.5 w-3.5"
                                :class="isSidebarFavoritePinned(item.key) ? 'fill-amber-400 text-amber-500' : ''"
                            />
                        </button>
                    </div>

                    <div
                        v-if="!collapsed && item.children?.length && isMenuGroupOpen(item.key)"
                        class="crm-nav-submenu-border ml-4 space-y-1 border-l border-zinc-200 pl-3 dark:border-zinc-800"
                    >
                        <div v-for="child in item.children" :key="child.key" class="space-y-1">
                            <div class="flex items-center gap-1">
                                <Link
                                    v-if="child.href && !child.disabled"
                                    :href="child.href"
                                    class="crm-nav-link flex min-w-0 flex-1 items-center px-3 py-2 text-left"
                                    :class="isMenuChildActive(child) ? 'crm-nav-link--active' : ''"
                                    @click="mobileMenuOpen = false"
                                >
                                    <span v-if="!collapsed" class="min-w-0 flex-1">
                                        <span class="block truncate">{{ child.label }}</span>
                                        <span
                                            v-if="child.hint"
                                            class="block truncate text-[11px] text-zinc-500 dark:text-zinc-400"
                                        >
                                            {{ child.hint }}
                                        </span>
                                    </span>
                                    <span v-else class="truncate">{{ child.label }}</span>
                                </Link>
                                <button
                                    v-else
                                    class="crm-nav-link flex min-w-0 flex-1 items-center px-3 py-2 text-left"
                                    :class="isMenuChildActive(child) ? 'crm-nav-link--active' : ''"
                                    :disabled="child.disabled"
                                    @click="child.disabled ? undefined : handleMenuSelect(child.key)"
                                >
                                    <span v-if="!collapsed" class="min-w-0 flex-1">
                                        <span class="block truncate">{{ child.label }}</span>
                                        <span
                                            v-if="child.hint"
                                            class="block truncate text-[11px] text-zinc-500 dark:text-zinc-400"
                                        >
                                            {{ child.hint }}
                                        </span>
                                    </span>
                                    <span v-else class="truncate">{{ child.label }}</span>
                                </button>

                                <button
                                    v-if="child.children?.length"
                                    type="button"
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                                    @click="toggleMenuGroup(child.key)"
                                >
                                    <ChevronDown class="h-4 w-4 transition-transform" :class="isMenuGroupOpen(child.key) ? 'rotate-180' : ''" />
                                </button>

                                <button
                                    v-if="canPinMenuKey(child.key)"
                                    type="button"
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-amber-500 dark:text-zinc-500 dark:hover:bg-zinc-800 dark:hover:text-amber-400"
                                    :title="isSidebarFavoritePinned(child.key) ? 'Убрать из избранного' : 'Закрепить в избранном'"
                                    :disabled="sidebarFavoriteSaving"
                                    @click.stop="toggleSidebarFavorite(child.key)"
                                >
                                    <Star
                                        class="h-3.5 w-3.5"
                                        :class="isSidebarFavoritePinned(child.key) ? 'fill-amber-400 text-amber-500' : ''"
                                    />
                                </button>
                            </div>

                            <div
                                v-if="child.children?.length && isMenuGroupOpen(child.key)"
                                class="crm-nav-submenu-border ml-3 space-y-1 border-l border-zinc-200 pl-3 dark:border-zinc-800"
                            >
                                <div
                                    v-for="grandChild in child.children"
                                    :key="grandChild.key"
                                    class="flex items-center gap-1"
                                >
                                    <button
                                        class="crm-nav-link flex min-w-0 flex-1 items-center px-3 py-2 text-left"
                                        :class="(activeSubKey === grandChild.key || activeLeafKey === grandChild.key) ? 'crm-nav-link--active' : ''"
                                        @click="handleMenuSelect(grandChild.key)"
                                    >
                                        {{ grandChild.label }}
                                    </button>
                                    <button
                                        v-if="canPinMenuKey(grandChild.key)"
                                        type="button"
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-amber-500 dark:text-zinc-500 dark:hover:bg-zinc-800 dark:hover:text-amber-400"
                                        :title="isSidebarFavoritePinned(grandChild.key) ? 'Убрать из избранного' : 'Закрепить в избранном'"
                                        :disabled="sidebarFavoriteSaving"
                                        @click.stop="toggleSidebarFavorite(grandChild.key)"
                                    >
                                        <Star
                                            class="h-3.5 w-3.5"
                                            :class="isSidebarFavoritePinned(grandChild.key) ? 'fill-amber-400 text-amber-500' : ''"
                                        />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="border-t border-zinc-200 p-4 dark:border-zinc-800">
                <div
                    v-if="collapsed"
                    class="mb-3 flex flex-col items-center gap-2"
                >
                    <button
                        type="button"
                        class="crm-layout-icon-button flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 transition-colors hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
                        title="Внешний вид"
                        @click="appearanceModalOpen = true"
                    >
                        <Palette class="h-4 w-4" />
                    </button>
                    <ThemeToggle />
                </div>
                <div v-if="!collapsed" class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-200 font-medium dark:bg-zinc-800">
                        {{ authUser?.name?.charAt(0)?.toUpperCase() || 'U' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium">{{ authUser?.name || 'Пользователь' }}</div>
                        <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ authUser?.email || '' }}</div>
                    </div>
                </div>
                <div v-else class="flex justify-center">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-200 text-sm font-medium dark:bg-zinc-800">
                        {{ authUser?.name?.charAt(0)?.toUpperCase() || 'U' }}
                    </div>
                </div>

                <div class="mt-3">
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-zinc-200 px-3 py-2 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        :class="collapsed ? 'px-2' : ''"
                        :title="collapsed ? 'Выйти' : false"
                    >
                        <LogOut class="h-4 w-4 shrink-0" />
                        <span v-if="!collapsed">Выйти</span>
                    </Link>
                </div>
            </div>
        </aside>

        <Teleport to="body">
            <div
                v-if="collapsedFlyout"
                class="fixed inset-0 z-[80]"
                aria-hidden="true"
                @click="closeCollapsedFlyout"
            />
            <div
                v-if="collapsedFlyout"
                class="fixed z-[81] min-w-[13rem] max-w-[min(100vw-1rem,20rem)] rounded-xl border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                :style="{ top: `${collapsedFlyout.top}px`, left: `${collapsedFlyout.left}px` }"
                role="menu"
            >
                <button
                    v-for="row in collapsedFlyout.items"
                    :key="row.key"
                    type="button"
                    role="menuitem"
                    class="flex w-full px-3 py-2 text-left text-sm transition-colors"
                    :class="isFlyoutNavKeyActive(row)
                        ? 'bg-zinc-100 font-medium text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100'
                        : 'text-zinc-700 hover:bg-zinc-50 dark:text-zinc-200 dark:hover:bg-zinc-800/80'"
                    @click="selectFlyoutNav(row)"
                >
                    {{ row.label }}
                </button>
            </div>
        </Teleport>

        <div v-if="!showMobileAppShell" :class="[collapsed ? 'lg:pl-20' : 'lg:pl-64', 'flex h-dvh max-h-dvh min-h-0 min-w-0 flex-1 flex-col overflow-hidden']">
            <header class="crm-layout-header flex items-center justify-between gap-3 border-b border-zinc-200 bg-zinc-50 px-3 py-3 dark:border-zinc-800 dark:bg-zinc-950 lg:hidden">
                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-xl border border-zinc-200 transition-colors hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    @click="mobileMenuOpen = true"
                >
                    <Menu class="h-5 w-5" />
                </button>

                <div class="min-w-0 flex-1" />

                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-xl border border-zinc-200 transition-colors hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    title="Внешний вид"
                    @click="appearanceModalOpen = true"
                >
                    <Palette class="h-4 w-4" />
                </button>
                <ThemeToggle />
            </header>

            <main
                class="crm-layout-main min-h-0 min-w-0 flex-1 bg-zinc-50 px-3 pt-0.5 dark:bg-zinc-950 md:px-4 md:pt-1.5"
                :class="mainFill
                    ? 'flex h-0 flex-col overflow-hidden pb-[88px] md:pb-[96px]'
                    : 'overflow-y-auto pb-[88px] lg:flex lg:h-0 lg:flex-col lg:overflow-hidden md:pb-[96px]'"
            >
                <div
                    v-if="flashBanner"
                    class="mb-3 shrink-0 rounded-lg border px-3 py-2 text-sm"
                    :class="flashBanner.type === 'error'
                        ? 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-100'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200'"
                    role="alert"
                >
                    {{ flashBanner.message }}
                </div>
                <div v-if="mainFill" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <slot />
                </div>
                <slot v-else />
            </main>

            <footer
                class="crm-layout-footer fixed bottom-0 left-0 right-0 z-50 shrink-0 border-t border-zinc-200 bg-zinc-50/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/95 transition-[left]"
                :class="collapsed ? 'lg:left-20' : 'lg:left-64'"
            >
                <div class="px-2 py-1.5 md:px-3">
                    <CrmCommandBar
                        :agent-message-count="agentMessages.length"
                        :agent-has-saved-thread="agentHasSavedThread"
                        :agent-history-limits="agentHistoryLimits"
                        :agent-extended-memory="agentExtendedMemory"
                        :selected-agent-slug="selectedAgentSlug"
                        @update:selected-agent-slug="onAgentSlugChange"
                        @submit="handleAiSubmit"
                        @badges="dynamicCabinetBadges = $event"
                        @agent-history-open="openAgentPanelFromHistory"
                        @agent-history-clear="clearAgentThread"
                        @agent-extended-memory-change="onAgentExtendedMemoryChange"
                    />
                    <CrmAgentPanel
                        :open="agentPanelOpen"
                        :messages="agentMessages"
                        :loading="agentLoading"
                        :error="agentError"
                        :channel="agentChannel"
                        :tool-rounds="agentToolRounds"
                        :feedback-busy-turn-id="agentFeedbackBusyTurnId"
                        @close="agentPanelOpen = false"
                        @feedback="submitAgentFeedback"
                    />
                </div>
            </footer>
        </div>

        <CrmAppearanceModal :show="appearanceModalOpen" @close="appearanceModalOpen = false" />

        <DocumentUploadOptimizeModal
            :show="documentUploadModalOpen"
            :state="documentUploadModalState"
            @accept="completeDocumentUpload($event)"
            @cancel="completeDocumentUpload(null)"
        />
    </div>
</template>

<script setup>
import { computed, onBeforeMount, onMounted, onUnmounted, ref, watch, watchEffect } from 'vue';
import { hasSalesAssistantSubmoduleAccess } from '@/support/crmVisibility.js';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    BarChart3,
    Bookmark,
    CalendarRange,
    ChevronDown,
    ClipboardList,
    Download,
    FileText,
    Gavel,
    House,
    Kanban,
    LayoutDashboard,
    LayoutGrid,
    LogOut,
    Mail,
    Menu,
    Package,
    PanelLeftClose,
    PanelLeftOpen,
    Puzzle,
    SquarePen,
    Settings,
    Smartphone,
    Star,
    Target,
    Route,
    Truck,
    Users,
    Wallet,
    Palette,
    WandSparkles,
} from 'lucide-vue-next';
import CrmAgentPanel from '@/Components/Layout/CrmAgentPanel.vue';
import CrmCommandBar from '@/Components/Layout/CrmCommandBar.vue';
import ThemeToggle from '@/Components/Layout/ThemeToggle.vue';
import CrmAppearanceModal from '@/Components/Crm/CrmAppearanceModal.vue';
import DocumentUploadOptimizeModal from '@/Components/Documents/DocumentUploadOptimizeModal.vue';
import { provideDocumentUploadGate } from '@/support/documentUploadGate.js';
import { crmBtnCreate } from '@/support/crmUi.js';
import {
    applyCrmAppearanceToDocument,
    resolveCrmAppearance,
} from '@/support/crmAppearance.js';
import { visitInertiaPath } from '@/support/inertiaHttpsVisit.js';
import {
    clearAgentThread as clearPersistedAgentThread,
    historyForAgentRequest,
    isAgentExtendedMemoryEnabled,
    loadAgentThread,
    resolveAgentHistoryLimits,
    saveAgentThread,
    setAgentExtendedMemoryEnabled,
} from '@/support/commandBarAgentHistory.js';
import { loadAgentSlug, saveAgentSlug } from '@/support/commandBarAgentPersona.js';

const props = defineProps({
    activeKey: {
        type: String,
        default: 'dashboard',
    },
    activeSubKey: {
        type: String,
        default: null,
    },
    activeLeafKey: {
        type: String,
        default: null,
    },
    mainFill: {
        type: Boolean,
        default: false,
    },
    showFlashBanner: {
        type: Boolean,
        default: true,
    },
});

const page = usePage();
const {
    modalOpen: documentUploadModalOpen,
    modalState: documentUploadModalState,
    complete: completeDocumentUpload,
} = provideDocumentUploadGate();

const flashBanner = computed(() => {
    const flash = page.props.flash;

    if (!props.showFlashBanner || !flash?.message) {
        return null;
    }

    return {
        type: flash.type === 'error' ? 'error' : 'success',
        message: flash.message,
    };
});
const menuStateStorageKey = 'crm-sidebar-expanded-groups';
const sidebarCollapsedStorageKey = 'crm-sidebar-collapsed';
const companyLogoSrc = '/assets/favicon/favicon-96x96.png';

function readSidebarCollapsedFromStorage() {
    if (typeof window === 'undefined') {
        return false;
    }
    try {
        return window.localStorage.getItem(sidebarCollapsedStorageKey) === '1';
    } catch {
        return false;
    }
}

function readExpandedGroupsFromStorage() {
    if (typeof window === 'undefined') {
        return [];
    }
    try {
        const raw = window.localStorage.getItem(menuStateStorageKey);
        if (! raw) {
            return [];
        }
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed.filter((item) => typeof item === 'string') : [];
    } catch {
        return [];
    }
}

/**
 * Какие группы меню должны быть раскрыты, чтобы был виден текущий раздел (без удаления пользовательских раскрытий).
 */
function requiredExpandedGroupKeys(activeKey, activeSubKey, activeLeafKey) {
    const keys = [];
    if (activeKey === 'finance') {
        keys.push('finance');
    }
    if (activeKey === 'settings') {
        keys.push('settings');
        const leaf = activeLeafKey ?? activeSubKey ?? '';
        if (['users', 'roles', 'business-processes'].includes(leaf)) {
            keys.push('administration');
        }
        if (['system', 'order-numbering'].includes(leaf)) {
            keys.push('system');
        }
        if (['table-presets', 'dictionaries', 'templates', 'mcp-integrations'].includes(leaf)) {
            keys.push('configuration');
        }
        if (activeSubKey === 'motivation' || ['kpi-settings', 'salary-settings'].includes(leaf)) {
            keys.push('motivation');
        }
    }
    if (activeKey === 'own-fleet') {
        keys.push('own-fleet');
    }
    if (activeKey === 'reports') {
        keys.push('reports');
    }
    if (activeKey === 'fleet') {
        keys.push('fleet');
    }
    if (activeKey === 'planning') {
        keys.push('planning');
    }
    if (activeKey === 'sales-assistant') {
        keys.push('sales-assistant');
    }
    if (activeKey === 'favorites') {
        keys.push('favorites');
    }
    return keys;
}

function sortMenuByLabel(items) {
    return [...items].sort((a, b) => String(a.label).localeCompare(String(b.label), 'ru'));
}

function withSortedChildren(item) {
    if (!item?.children?.length) {
        return item;
    }

    return {
        ...item,
        children: sortMenuByLabel(item.children.map((child) => (
            child.children?.length ? withSortedChildren(child) : child
        ))),
    };
}

function applyRouteToExpandedGroups() {
    const required = requiredExpandedGroupKeys(props.activeKey, props.activeSubKey, props.activeLeafKey);
    const routeKeys = [props.activeKey, props.activeSubKey, props.activeLeafKey].filter(Boolean);
    if (routeKeys.some((key) => sidebarFavoriteKeys.value.includes(key))) {
        required.push('favorites');
    }
    if (required.length === 0) {
        return;
    }
    expandedGroups.value = [...new Set([...expandedGroups.value, ...required])];
}

const collapsed = ref(readSidebarCollapsedFromStorage());
const expandedGroups = ref(readExpandedGroupsFromStorage());
const mobileMenuOpen = ref(false);
const collapsedFlyout = ref(null);
const deferredInstallPrompt = ref(null);
const isStandaloneApp = ref(false);
const isMobileViewport = ref(false);

const authUser = computed(() => page.props.auth?.user ?? null);
const sidebarFavorites = computed(() => authUser.value?.sidebar_favorites ?? null);
const sidebarFavoriteKeys = computed(() => sidebarFavorites.value?.keys ?? []);
const sidebarFavoriteMax = computed(() => sidebarFavorites.value?.max ?? 5);
const sidebarFavoriteSaving = ref(false);
const sidebarFavoriteError = ref('');
const appearanceModalOpen = ref(false);
const dynamicCabinetBadges = ref(null);
const cabinetBadges = computed(
    () => dynamicCabinetBadges.value ?? page.props.cabinet_notification_badges ?? { total: 0, orders: 0, tasks: 0 },
);

function menuBadgeFor(key) {
    if (key === 'orders') {
        return cabinetBadges.value.orders ?? 0;
    }
    if (key === 'tasks' || key === 'planning') {
        return cabinetBadges.value.tasks ?? 0;
    }

    return 0;
}
const visibleAreas = computed(() => authUser.value?.role?.visibility_areas ?? []);
const isAdminUser = computed(() => Boolean(authUser.value?.role?.is_admin) || authUser.value?.role?.name === 'admin');
const hasLegacyAllSettingsAccess = computed(() => {
    const areas = visibleAreas.value;
    return areas.includes('settings') && !areas.includes('settings_system') && !areas.includes('settings_motivation');
});
const hasSettingsSystemAccess = computed(() => {
    const areas = visibleAreas.value;
    return isAdminUser.value || hasLegacyAllSettingsAccess.value || areas.includes('settings_system');
});
const hasSettingsMotivationAccess = computed(() => {
    const areas = visibleAreas.value;
    return isAdminUser.value || hasLegacyAllSettingsAccess.value || areas.includes('settings_motivation');
});
const hasFinanceSalaryAccess = computed(() => isAdminUser.value || visibleAreas.value.includes('finance_salary'));
const hasManagementAccess = computed(() => isAdminUser.value || Boolean(authUser.value?.belongs_to_management));
const hasCompanyPlanningAccess = computed(() => isAdminUser.value || (hasManagementAccess.value && visibleAreas.value.includes('company_planning')));
const hasManagementAccountingAccess = computed(() => isAdminUser.value || Boolean(authUser.value?.can_management_accounting));

function canPinMenuKey(key) {
    if (!key || !MENU_ROUTES[key]) {
        return false;
    }

    const candidates = sidebarFavorites.value?.candidate_keys;
    if (!Array.isArray(candidates)) {
        return false;
    }

    return candidates.includes(key);
}

function isSidebarFavoritePinned(key) {
    return sidebarFavoriteKeys.value.includes(key);
}

function toggleSidebarFavorite(key) {
    if (!canPinMenuKey(key) || sidebarFavoriteSaving.value) {
        return;
    }

    const current = [...sidebarFavoriteKeys.value];
    const idx = current.indexOf(key);
    let next;

    if (idx >= 0) {
        next = current.filter((item) => item !== key);
    } else {
        if (current.length >= sidebarFavoriteMax.value) {
            sidebarFavoriteError.value = `Можно закрепить не более ${sidebarFavoriteMax.value} пунктов меню`;
            return;
        }

        next = [...current, key];
    }

    sidebarFavoriteError.value = '';
    sidebarFavoriteSaving.value = true;

    router.patch(route('profile.sidebar-favorites'), { sidebar_favorite_keys: next }, {
        preserveScroll: true,
        onError: (errors) => {
            const messages = Object.values(errors ?? {}).flat().filter(Boolean);
            sidebarFavoriteError.value = messages.length
                ? messages.join(' ')
                : 'Не удалось обновить избранное.';
        },
        onFinish: () => {
            sidebarFavoriteSaving.value = false;
        },
    });
}

const MENU_ROUTES = {
    dashboard: '/dashboard',
    leads: '/leads',
    orders: '/orders',
    'load-board': '/load-board',
    tasks: '/tasks',
    kanban: '/kanban',
    disposition: '/disposition',
    pipeline: '/pipeline',
    'company-planning': '/company-planning',
    'orders-create': '/orders/create',
    contractors: '/contractors',
    'fleet-vehicles': '/fleet/vehicles',
    'fleet-trips': '/fleet/trips',
    'fleet-efficiency': '/fleet/efficiency',
    'fleet-containers': '/fleet/containers',
    'fleet-drivers': '/drivers',
    documents: '/documents',
    mail: '/mail',
    finance: '/finance',
    'finance-cashflow': '/finance?section=cashflow',
    'finance-reconciliation': '/finance/reconciliation',
    'finance-salary': '/finance/salary',
    'finance-budgeting': '/budgeting',
    'finance-management-accounting': '/finance/management-accounting',
    reports: '/reports',
    'reports-overview': '/reports',
    trainer: '/sales-assistant/trainer',
    modules: '/modules',
    'sales-assistant-counter': '/sales-assistant/counter',
    'modules-how-much-fits': '/modules/how-much-fits',
    'modules-how-much-costs': '/modules/how-much-costs',
    'modules-import-cost': '/modules/import-cost',
    'modules-proposal-templates': '/modules/proposal-templates',
    'sales-assistant-scripts': '/scripts',
    'sales-assistant-book': '/sales-assistant/book',
    'sales-assistant-book-quiz-analytics': '/sales-assistant/book/quiz-analytics',
    'sales-assistant-trainer': '/sales-assistant/trainer',
    'sales-assistant-trainer-analytics': '/sales-assistant/trainer/analytics',
    settings: '/settings',
    users: '/settings/users',
    roles: '/settings/roles',
    'business-processes': '/settings/business-processes',
    'table-presets': '/settings/tables',
    dictionaries: '/settings/dictionaries',
    templates: '/settings/templates',
    'mcp-integrations': '/settings/mcp-integrations',
    motivation: '/settings/motivation',
    'kpi-settings': '/settings/motivation/kpi',
    'salary-settings': '/settings/motivation/salary',
    'ai-analytics': '/settings/ai-analytics',
    system: '/settings/system',
    'order-numbering': '/settings/system/order-numbering',
};

const MOBILE_BROWSER_BYPASS = 'crm_mobile_browser_cabinet_v1';

function readMobileBrowserBypassFromStorage() {
    if (typeof window === 'undefined') {
        return false;
    }
    try {
        return window.localStorage.getItem(MOBILE_BROWSER_BYPASS) === '1';
    } catch {
        return false;
    }
}

const allowMobileBrowserCabinet = ref(false);

function continueMobileBrowserCabinet() {
    try {
        window.localStorage.setItem(MOBILE_BROWSER_BYPASS, '1');
    } catch {
        /* ignore */
    }
    allowMobileBrowserCabinet.value = true;
}

const MOBILE_NAV_DEF = [
    { key: 'dashboard', label: 'Главная', icon: House },
    { key: 'orders', label: 'Заказы', icon: Package },
    { key: 'load-board', label: 'Биржа', icon: Gavel },
    { key: 'leads', label: 'Лиды', icon: Target },
    { key: 'tasks', label: 'Задачи', icon: ClipboardList },
    { key: 'kanban', label: 'Канбан', icon: Kanban },
    { key: 'documents', label: 'Документы', icon: FileText },
    { key: 'reports', label: 'Отчёты', icon: BarChart3 },
    { key: 'finance', label: 'Финансы', icon: Wallet },
    { key: 'trainer', label: 'Тренажёр', icon: WandSparkles },
    { key: 'orders-create', label: 'Новый', icon: SquarePen },
    { key: 'contractors', label: 'База', icon: Users },
];

const MOBILE_NAV_SELECTABLE_KEYS = [
    'dashboard',
    'orders',
    'load-board',
    'leads',
    'tasks',
    'kanban',
    'documents',
    'reports',
    'finance',
    'trainer',
];

function mobileNavItemsLegacy() {
    const items = MOBILE_NAV_DEF.filter((item) => MOBILE_NAV_SELECTABLE_KEYS.includes(item.key));

    return items.filter((item) => {
        if (isAdminUser.value) {
            return true;
        }

        if (item.key === 'dashboard') {
            return visibleAreas.value.includes('dashboard');
        }

        if (item.key === 'kanban') {
            return visibleAreas.value.includes('kanban') || visibleAreas.value.includes('tasks');
        }

        if (item.key === 'load-board') {
            return visibleAreas.value.includes('load_board');
        }

        if (item.key === 'trainer') {
            return visibleAreas.value.includes('sales_assistant_trainer')
                || visibleAreas.value.includes('scripts');
        }

        if (item.key === 'finance') {
            return visibleAreas.value.includes('finance')
                || visibleAreas.value.includes('finance_salary')
                || visibleAreas.value.includes('budgeting');
        }

        return visibleAreas.value.includes(item.key);
    });
}

const mobileNavItems = computed(() => {
    const resolved = authUser.value?.mobile_nav?.resolved_keys;
    if (Array.isArray(resolved) && resolved.length > 0) {
        return resolved
            .map((key) => MOBILE_NAV_DEF.find((i) => i.key === key))
            .filter(Boolean);
    }

    return mobileNavItemsLegacy();
});

const mobileNavModalOpen = ref(false);
const mobileNavDraftKeys = ref([]);
const mobileNavDraftError = ref('');
const mobileNavSaving = ref(false);

const mobileNavCandidateEntries = computed(() => {
    const labels = authUser.value?.mobile_nav?.labels ?? {};
    const candidates = authUser.value?.mobile_nav?.candidate_keys ?? [];
    const order = MOBILE_NAV_DEF.map((i) => i.key);

    return order
        .filter((key) => candidates.includes(key))
        .map((key) => ({
            key,
            label: labels[key] || MOBILE_NAV_DEF.find((i) => i.key === key)?.label || key,
        }));
});

function openMobileNavModal() {
    const cur = authUser.value?.mobile_nav?.resolved_keys;
    const fallback = [...(authUser.value?.mobile_nav?.candidate_keys ?? [])].slice(0, 6);
    mobileNavDraftKeys.value = Array.isArray(cur) && cur.length ? [...cur] : [...fallback];
    mobileNavDraftError.value = '';
    mobileNavModalOpen.value = true;
}

function toggleMobileNavDraftKey(key) {
    const arr = [...mobileNavDraftKeys.value];
    const i = arr.indexOf(key);

    if (i >= 0) {
        arr.splice(i, 1);
        mobileNavDraftError.value = '';
        mobileNavDraftKeys.value = arr;

        return;
    }

    if (arr.length >= 6) {
        mobileNavDraftError.value = 'Можно выбрать не больше 6 пунктов. Снимите галочку с другого пункта.';

        return;
    }

    arr.push(key);
    mobileNavDraftError.value = '';
    mobileNavDraftKeys.value = arr;
}

function saveMobileNavDraft() {
    if (mobileNavSaving.value) {
        return;
    }

    mobileNavDraftError.value = '';
    mobileNavSaving.value = true;

    router.patch(route('profile.mobile-bottom-nav'), { mobile_nav_keys: mobileNavDraftKeys.value }, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            mobileNavModalOpen.value = false;
        },
        onError: (errors) => {
            const messages = Object.values(errors ?? {}).flat().filter(Boolean);

            mobileNavDraftError.value = messages.length
                ? messages.join(' ')
                : 'Не удалось сохранить настройки меню.';
        },
        onFinish: () => {
            mobileNavSaving.value = false;
        },
    });
}

const mobileNavPresets = computed(() => {
    const list = page.props.mobile_nav_presets;

    return Array.isArray(list) ? list : [];
});

function applyMobileNavPreset(preset) {
    const candidates = authUser.value?.mobile_nav?.candidate_keys ?? [];
    const allowed = new Set(candidates);
    const keys = (preset?.keys ?? []).filter((key) => allowed.has(key)).slice(0, 6);

    mobileNavDraftKeys.value = keys;
    mobileNavDraftError.value = keys.length === 0
        ? 'Для этой роли недоступны пункты из выбранного набора. Проверьте области видимости.'
        : '';
}

function resetMobileNavDraft() {
    if (mobileNavSaving.value) {
        return;
    }

    mobileNavDraftError.value = '';
    mobileNavSaving.value = true;

    router.patch(route('profile.mobile-bottom-nav'), { mobile_nav_keys: [] }, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            mobileNavDraftKeys.value = [];
            mobileNavModalOpen.value = false;
        },
        onError: (errors) => {
            const messages = Object.values(errors ?? {}).flat().filter(Boolean);

            mobileNavDraftError.value = messages.length
                ? messages.join(' ')
                : 'Не удалось сбросить настройки меню.';
        },
        onFinish: () => {
            mobileNavSaving.value = false;
        },
    });
}

const showMobileAppGate = computed(() => isMobileViewport.value && !isStandaloneApp.value && !allowMobileBrowserCabinet.value);
const showMobileAppShell = computed(() => isMobileViewport.value && isStandaloneApp.value);
const canInstallApp = computed(() => deferredInstallPrompt.value !== null);

const menuItems = computed(() => {
    const areas = visibleAreas.value;
    const isAdmin = isAdminUser.value;
    const planningChildren = [];
    if (isAdmin || areas.includes('tasks')) {
        planningChildren.push({ key: 'tasks', label: 'Задачи' });
    }
    if (isAdmin || areas.includes('kanban') || areas.includes('tasks')) {
        planningChildren.push({ key: 'kanban', label: 'Канбан' });
    }
    if (isAdmin || areas.includes('orders')) {
        planningChildren.push({ key: 'disposition', label: 'Диспозиция' });
    }
    if (isAdmin || areas.includes('pipeline')) {
        planningChildren.push({ key: 'pipeline', label: 'Pipeline' });
    }
    if (hasCompanyPlanningAccess.value) {
        planningChildren.push({ key: 'company-planning', label: 'План компании' });
    }
    const planningItem =
        planningChildren.length > 0
            ? {
                key: 'planning',
                label: 'Планирование',
                icon: CalendarRange,
                children: planningChildren,
            }
            : null;
    const assistantParts = [
        { area: 'sales_assistant_scripts', key: 'sales-assistant-scripts', label: 'Скрипты' },
        { area: 'sales_assistant_book', key: 'sales-assistant-book', label: 'Книга продаж' },
        { area: 'sales_assistant_trainer', key: 'sales-assistant-trainer', label: 'Тренажёр' },
        { area: 'sales_assistant_counter', key: 'sales-assistant-counter', label: 'Считалка' },
    ];
    const salesAssistantChildren = assistantParts.filter(
        (p) => isAdmin || hasSalesAssistantSubmoduleAccess(areas, p.area),
    );
    const salesAssistantItem =
        salesAssistantChildren.length > 0
            ? {
                key: 'sales-assistant',
                label: 'Помощник продавца',
                icon: WandSparkles,
                children: salesAssistantChildren.map(({ key, label }) => ({ key, label })),
            }
            : null;

    const favoriteMenuItems = sortMenuByLabel(
        (sidebarFavorites.value?.items ?? []).map((entry) => ({
            key: entry.key,
            label: entry.label,
            href: entry.href,
        })),
    );

    const favoritesItem = authUser.value
        ? {
            key: 'favorites',
            label: 'Избранное',
            icon: Bookmark,
            children: favoriteMenuItems.length > 0
                ? favoriteMenuItems
                : [{
                    key: 'favorites-empty',
                    label: 'Нет закреплённых пунктов',
                    hint: 'Нажмите ★ у часто используемых разделов',
                    disabled: true,
                }],
        }
        : null;

    const items = [
        { key: 'dashboard', label: 'Дашборд', icon: LayoutDashboard, visibilityArea: 'dashboard' },
        ...(favoritesItem ? [favoritesItem] : []),
        { key: 'leads', label: 'Лиды', icon: Target, visibilityArea: 'leads' },
        { key: 'orders', label: 'Заказы', icon: Package, visibilityArea: 'orders' },
        { key: 'load-board', label: 'Биржа грузов', icon: Gavel, visibilityArea: 'load_board' },
        { key: 'contractors', label: 'Контрагенты', icon: Users, visibilityArea: 'contractors' },
        {
            key: 'fleet',
            label: 'ТС',
            icon: Truck,
            visibilityArea: 'drivers',
            children: [
                { key: 'fleet-vehicles', label: 'Авто' },
                { key: 'fleet-containers', label: 'Контейнера' },
                { key: 'fleet-drivers', label: 'Водители' },
            ],
        },
        {
            key: 'own-fleet',
            label: 'Собственный парк',
            icon: Route,
            visibilityArea: 'own_fleet',
            children: [
                { key: 'fleet-trips', label: 'Рейсы' },
                { key: 'fleet-efficiency', label: 'Эффективность' },
            ],
        },
        { key: 'documents', label: 'Документы', icon: FileText, visibilityArea: 'documents' },
        { key: 'mail', label: 'Почта', icon: Mail, visibilityArea: 'mail' },
        {
            key: 'finance',
            label: 'Финансы',
            icon: Wallet,
            visibilityArea: 'documents',
            children: (() => {
                const children = [];

                if (visibleAreas.value.includes('documents') || visibleAreas.value.includes('payment_schedules')) {
                    children.push({ key: 'finance-cashflow', label: 'График оплат' });
                    children.push({ key: 'finance-reconciliation', label: 'Акты сверок' });
                }

                if (hasFinanceSalaryAccess.value) {
                    children.push({ key: 'finance-salary', label: 'Зарплата' });
                }

                if (hasManagementAccess.value) {
                    children.push({ key: 'finance-budgeting', label: 'Бюджетирование' });
                }

                if (hasManagementAccountingAccess.value) {
                    children.push({ key: 'finance-management-accounting', label: 'Управленческий учёт' });
                }

                return children;
            })(),
        },
        ...(planningItem ? [planningItem] : []),
        ...(salesAssistantItem ? [salesAssistantItem] : []),
        ...(() => {
            const reportChildren = [];

            if (isAdmin || areas.includes('reports')) {
                reportChildren.push({ key: 'reports-overview', label: 'Сводные отчёты' });
            }

            if (hasSettingsSystemAccess.value) {
                reportChildren.push({ key: 'ai-analytics', label: 'Аналитика AI' });
            }

            if (isAdmin || hasSalesAssistantSubmoduleAccess(areas, 'sales_assistant_trainer_analytics')) {
                reportChildren.push({ key: 'sales-assistant-trainer-analytics', label: 'Аналитика тренажёра' });
            }

            if (isAdmin || hasSalesAssistantSubmoduleAccess(areas, 'sales_assistant_book_analytics')) {
                reportChildren.push({ key: 'sales-assistant-book-quiz-analytics', label: 'Статистика тестов' });
            }

            return reportChildren.length > 0
                ? [{
                    key: 'reports',
                    label: 'Отчёты',
                    icon: BarChart3,
                    visibilityArea: 'reports',
                    children: reportChildren,
                }]
                : [];
        })(),
        ...(() => {
            const moduleParts = [
                { area: 'modules_how_much_fits', key: 'modules-how-much-fits', label: 'Сколько влезет?' },
                { area: 'modules_how_much_costs', key: 'modules-how-much-costs', label: 'Сколько стоит?' },
                { area: 'modules_import_cost', key: 'modules-import-cost', label: 'Растаможка' },
                { area: 'modules_proposal_templates', key: 'modules-proposal-templates', label: 'Шаблоны КП' },
            ];
            const moduleChildren = moduleParts.filter((part) => {
                if (isAdmin || areas.includes('modules') || areas.includes(part.area)) {
                    return true;
                }

                if (
                    part.area === 'modules_proposal_templates'
                    && (areas.includes('settings') || areas.includes('settings_system'))
                ) {
                    return true;
                }

                return false;
            });

            return moduleChildren.length > 0
                ? [{
                    key: 'modules',
                    label: 'Модули',
                    icon: Puzzle,
                    visibilityArea: 'modules',
                    children: moduleChildren.map(({ key, label }) => ({ key, label })),
                }]
                : [];
        })(),
        {
            key: 'settings',
            label: 'Настройки',
            icon: Settings,
            children: (() => {
                const children = [];
                const administrationChildren = [];
                if (hasSettingsSystemAccess.value) {
                    administrationChildren.push({ key: 'users', label: 'Пользователи' });
                }
                if (isAdminUser.value) {
                    administrationChildren.push({ key: 'roles', label: 'Роли' });
                }
                if (hasSettingsSystemAccess.value) {
                    administrationChildren.push({ key: 'business-processes', label: 'Бизнес-процессы' });
                }
                if (administrationChildren.length > 0) {
                    children.push({
                        key: 'administration',
                        label: 'Администрирование',
                        children: administrationChildren,
                    });
                }
                if (hasSettingsSystemAccess.value) {
                    children.push({
                        key: 'configuration',
                        label: 'Конфигурация',
                        children: [
                            { key: 'table-presets', label: 'Управление таблицами' },
                            { key: 'dictionaries', label: 'Справочники' },
                            { key: 'templates', label: 'Шаблоны' },
                            { key: 'mcp-integrations', label: 'Связи MCP' },
                        ],
                    });
                }
                if (hasSettingsSystemAccess.value) {
                    children.push({
                        key: 'system',
                        label: 'Системные',
                        children: [
                            { key: 'order-numbering', label: 'Автонумератор' },
                        ],
                    });
                }
                if (hasSettingsMotivationAccess.value) {
                    children.push({
                        key: 'motivation',
                        label: 'Мотивация',
                        children: [
                            { key: 'kpi-settings', label: 'Настройки вычетов' },
                            { key: 'salary-settings', label: 'Персональные условия' },
                        ],
                    });
                }
                return children;
            })(),
        },
    ];

    return items.filter((item) => {
        if (isAdminUser.value) {
            return true;
        }

        if (item.key === 'favorites') {
            return true;
        }

        if (item.key === 'settings') {
            return hasSettingsSystemAccess.value || hasSettingsMotivationAccess.value;
        }

        if (item.key === 'finance') {
            return (item.children?.length ?? 0) > 0 || hasManagementAccess.value;
        }

        if (item.key === 'fleet') {
            return visibleAreas.value.includes('drivers');
        }

        if (item.key === 'own-fleet') {
            return visibleAreas.value.includes('own_fleet')
                || visibleAreas.value.includes('fleet_trips')
                || visibleAreas.value.includes('fleet_efficiency')
                || visibleAreas.value.includes('drivers');
        }

        if (item.key === 'reports') {
            return (item.children?.length ?? 0) > 0;
        }

        if (item.key === 'planning') {
            return (item.children?.length ?? 0) > 0;
        }

        if (item.key === 'sales-assistant') {
            return (item.children?.length ?? 0) > 0;
        }

        if (item.key === 'modules') {
            return (item.children?.length ?? 0) > 0;
        }

        if (item.key === 'finance') {
            return (item.children?.length ?? 0) > 0;
        }

        if (!item.visibilityArea) {
            return true;
        }

        return visibleAreas.value.includes(item.visibilityArea);
    }).map(withSortedChildren);
});

watch(
    () => [props.activeKey, props.activeSubKey, props.activeLeafKey],
    () => {
        applyRouteToExpandedGroups();
    },
);

watch(
    expandedGroups,
    (value) => {
        localStorage.setItem(menuStateStorageKey, JSON.stringify(value));
    },
    { deep: true },
);

watch(collapsed, (value) => {
    if (!value) {
        closeCollapsedFlyout();
    }
    try {
        localStorage.setItem(sidebarCollapsedStorageKey, value ? '1' : '0');
    } catch {
        /* ignore */
    }
});

watchEffect((onCleanup) => {
    if (!collapsedFlyout.value) {
        return;
    }
    function onKeydown(e) {
        if (e.key === 'Escape') {
            closeCollapsedFlyout();
        }
    }
    window.addEventListener('keydown', onKeydown);
    onCleanup(() => window.removeEventListener('keydown', onKeydown));
});

watch(
    mobileMenuOpen,
    (value) => {
        document.body.classList.toggle('overflow-hidden', value);
    },
);

watch(
    showMobileAppGate,
    (value) => {
        if (value) {
            mobileMenuOpen.value = false;
        }
    },
    { immediate: true },
);

onBeforeMount(() => {
    applyRouteToExpandedGroups();
    completeDocumentUpload(null);
    document.body.style.overflow = '';
});

watch(
    () => authUser.value?.ui_preferences,
    () => {
        applyCrmAppearanceToDocument(resolveCrmAppearance(authUser.value));
    },
    { deep: true },
);

onMounted(() => {
    applyCrmAppearanceToDocument(resolveCrmAppearance(authUser.value));
    allowMobileBrowserCabinet.value = readMobileBrowserBypassFromStorage();
    updateMobileEnvironment();
    document.documentElement.classList.add('crm-layout-scroll-lock');

    window.addEventListener('resize', updateMobileEnvironment);
    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    window.addEventListener('appinstalled', handleAppInstalled);
});

onUnmounted(() => {
    document.body.classList.remove('overflow-hidden');
    document.documentElement.classList.remove('crm-layout-scroll-lock');
    window.removeEventListener('resize', updateMobileEnvironment);
    window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    window.removeEventListener('appinstalled', handleAppInstalled);
});

function updateMobileEnvironment() {
    isMobileViewport.value = window.matchMedia('(max-width: 1023px)').matches;
    isStandaloneApp.value = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function handleBeforeInstallPrompt(event) {
    event.preventDefault();
    deferredInstallPrompt.value = event;
}

function handleAppInstalled() {
    deferredInstallPrompt.value = null;
    updateMobileEnvironment();
}

async function triggerInstallPrompt() {
    if (!deferredInstallPrompt.value) {
        return;
    }

    deferredInstallPrompt.value.prompt();
    await deferredInstallPrompt.value.userChoice;
    deferredInstallPrompt.value = null;
}

function isMenuGroupOpen(key) {
    return expandedGroups.value.includes(key);
}

function toggleMenuGroup(key) {
    expandedGroups.value = isMenuGroupOpen(key)
        ? expandedGroups.value.filter((item) => item !== key)
        : [...expandedGroups.value, key];
}

function isMenuChildActive(child) {
    if (props.activeKey === child.key || props.activeSubKey === child.key || props.activeLeafKey === child.key) {
        return true;
    }

    return child.children?.some(
        (grandChild) => grandChild.key === props.activeSubKey || grandChild.key === props.activeLeafKey,
    ) ?? false;
}

function closeCollapsedFlyout() {
    collapsedFlyout.value = null;
}

function collectMenuLeaves(menuItem, prefix = '') {
    const out = [];
    for (const n of menuItem.children || []) {
        if (n.children?.length) {
            const nextPrefix = prefix ? `${prefix}${n.label} — ` : `${n.label} — `;
            out.push(...collectMenuLeaves(n, nextPrefix));
        } else if (MENU_ROUTES[n.key]) {
            out.push({ key: n.key, label: prefix + n.label });
        }
    }
    return out;
}

function openCollapsedFlyout(item, event) {
    const el = event?.currentTarget;
    if (!(el instanceof HTMLElement)) {
        return;
    }
    const items = collectMenuLeaves(item, '');
    if (items.length === 0) {
        return;
    }
    const rect = el.getBoundingClientRect();
    const panelWidth = 220;
    const left = Math.min(rect.right + 8, window.innerWidth - panelWidth);
    collapsedFlyout.value = {
        parentKey: item.key,
        top: rect.top,
        left: Math.max(8, left),
        items,
    };
}

function isFlyoutNavKeyActive(row) {
    return row.key === props.activeSubKey || row.key === props.activeLeafKey || row.key === props.activeKey;
}

function selectFlyoutNav(row) {
    closeCollapsedFlyout();

    if (row.href) {
        mobileMenuOpen.value = false;
        visitInertiaPath(row.href);

        return;
    }

    handleMenuSelect(row.key);
}

function isMobileNavItemActive(key) {
    if (key === 'trainer') {
        return props.activeKey === 'sales-assistant'
            && (props.activeSubKey === 'sales-assistant-trainer'
                || props.activeSubKey === 'sales-assistant-trainer-analytics');
    }

    return props.activeKey === key;
}

function handleMenuSelect(key, event) {
    const topItem = menuItems.value.find((i) => i.key === key);

    if (collapsed.value && topItem?.children?.length) {
        if (collapsedFlyout.value?.parentKey === key) {
            closeCollapsedFlyout();
        } else {
            openCollapsedFlyout(topItem, event);
        }
        return;
    }

    closeCollapsedFlyout();

    if (['settings', 'administration', 'configuration', 'motivation', 'finance', 'fleet', 'sales-assistant', 'planning', 'modules', 'favorites'].includes(key)) {
        toggleMenuGroup(key);
    }

    if (MENU_ROUTES[key]) {
        mobileMenuOpen.value = false;
        visitInertiaPath(MENU_ROUTES[key]);
    }
}

const agentPanelOpen = ref(false);
const agentHistoryLimits = computed(() => resolveAgentHistoryLimits(page.props));
const agentExtendedMemory = ref(isAgentExtendedMemoryEnabled());
const agentMessages = ref(loadAgentThread(agentHistoryLimits.value, agentExtendedMemory.value));
const agentHasSavedThread = ref(agentMessages.value.length > 0);
const selectedAgentSlug = ref(loadAgentSlug(String(page.props.ai_agent_default_slug ?? 'jarvis')));
const agentLoading = ref(false);
const agentError = ref('');
const agentChannel = ref('');
const agentToolRounds = ref(0);
const agentFeedbackBusyTurnId = ref('');

function onAgentSlugChange(slug) {
    selectedAgentSlug.value = String(slug);
    saveAgentSlug(selectedAgentSlug.value);
}

function onAgentExtendedMemoryChange(enabled) {
    agentExtendedMemory.value = enabled;
    setAgentExtendedMemoryEnabled(enabled);
    agentMessages.value = loadAgentThread(agentHistoryLimits.value, enabled);
}

async function handleAiSubmit(payload) {
    const text = String(payload?.message ?? '').trim();
    const files = Array.isArray(payload?.files) ? payload.files : [];

    if (text === '' && files.length === 0) {
        return;
    }

    agentPanelOpen.value = true;
    agentError.value = '';

    const history = historyForAgentRequest(
        agentMessages.value,
        agentHistoryLimits.value,
        agentExtendedMemory.value,
    );

    const displayContent = files.length > 0
        ? `[Файлы: ${files.map((file) => file.name).join(', ')}]${text !== '' ? `\n${text}` : ''}`
        : text;

    agentMessages.value.push({ role: 'user', content: displayContent });
    agentLoading.value = true;

    try {
        const agentSlug = payload?.agent_slug ?? selectedAgentSlug.value;
        let data;

        if (files.length > 0) {
            const formData = new FormData();
            formData.append('message', text || 'Обработай приложенные файлы согласно моему запросу.');
            formData.append('agent_slug', agentSlug);
            formData.append('history', JSON.stringify(history));
            formData.append('history_extended', agentExtendedMemory.value ? '1' : '0');
            files.forEach((file, index) => {
                formData.append(`attachments[${index}]`, file);
            });

            ({ data } = await axios.post(route('agent.command-bar.chat'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
                timeout: 300_000,
            }));
        } else {
            ({ data } = await axios.post(route('agent.command-bar.chat'), {
                message: text,
                history,
                agent_slug: agentSlug,
                history_extended: agentExtendedMemory.value,
            }, {
                timeout: 300_000,
            }));
        }

        agentMessages.value.push({
            role: 'assistant',
            content: String(data?.reply ?? 'Пустой ответ.'),
            turnId: data?.turn_id ? String(data.turn_id) : null,
            feedback: null,
        });
        agentChannel.value = String(data?.channel ?? '');
        agentToolRounds.value = Number(data?.tool_rounds ?? 0);

        const navigateTo = String(data?.navigate_to ?? '').trim();
        if (navigateTo !== '') {
            visitInertiaPath(navigateTo);
        }
    } catch (error) {
        console.error('Command bar agent request failed', error);

        const message = error?.response?.data?.message
            ?? error?.response?.data?.errors?.message?.[0]
            ?? error?.response?.data?.errors?.['attachments.0']?.[0]
            ?? (error?.response?.status === 504 || error?.code === 'ECONNABORTED'
                ? 'Ассистент не успел ответить за отведённое время. Попробуйте короче запрос или без вложений.'
                : error?.response?.status === 401
                    ? 'Сессия истекла — обновите страницу (F5) и войдите снова.'
                    : error?.response?.status === 419
                        ? 'Сессия истекла — обновите страницу (F5).'
                        : 'Не удалось связаться с ассистентом. Проверьте DEEPSEEK_API_KEY и логи сервера.');

        agentError.value = message;
    } finally {
        agentLoading.value = false;
    }
}

async function submitAgentFeedback({ turnId, rating }) {
    if (!turnId || agentFeedbackBusyTurnId.value) {
        return;
    }

    agentFeedbackBusyTurnId.value = turnId;

    try {
        await axios.post(route('agent.command-bar.feedback'), {
            turn_id: turnId,
            rating,
        });

        agentMessages.value = agentMessages.value.map((item) => (
            item.turnId === turnId ? { ...item, feedback: rating } : item
        ));
    } catch (error) {
        console.error('Command bar feedback failed', error);
    } finally {
        agentFeedbackBusyTurnId.value = '';
    }
}

watch(
    agentMessages,
    (messages) => {
        saveAgentThread(messages, agentHistoryLimits.value, agentExtendedMemory.value);
        agentHasSavedThread.value = messages.length > 0;
    },
    { deep: true },
);

function openAgentPanelFromHistory() {
    agentPanelOpen.value = true;
    agentError.value = '';
}

function clearAgentThread() {
    if (agentMessages.value.length === 0) {
        clearPersistedAgentThread();
        agentHasSavedThread.value = false;

        return;
    }

    if (!window.confirm('Очистить историю диалога с ассистентом на этом устройстве?')) {
        return;
    }

    agentMessages.value = [];
    agentError.value = '';
    clearPersistedAgentThread();
    agentHasSavedThread.value = false;
}
</script>
