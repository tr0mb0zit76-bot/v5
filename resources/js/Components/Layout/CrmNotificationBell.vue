<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';

const emit = defineEmits(['badges']);

const props = defineProps({
    /** Размер как у кнопок в CrmCommandBar (h-11, rounded-2xl). */
    large: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const open = ref(false);
const items = ref([]);
const loading = ref(false);
const toast = ref(null);
const localBadges = ref({ total: 0, orders: 0, tasks: 0 });
let pollTimer = null;
let lastPolledTotal = 0;

const authUser = computed(() => page.props.auth?.user ?? null);

function csrfHeaders() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token ?? '',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function normalizeActionUrl(url) {
    if (!url || typeof url !== 'string') {
        return '/';
    }

    try {
        const parsed = new URL(url, window.location.origin);

        if (parsed.host === window.location.host) {
            return `${parsed.pathname}${parsed.search}${parsed.hash}`;
        }

        return parsed.toString();
    } catch {
        return url;
    }
}

function initialBadges() {
    return page.props.cabinet_notification_badges ?? { total: 0, orders: 0, tasks: 0 };
}

async function fetchList() {
    if (!authUser.value) {
        return;
    }
    loading.value = true;
    try {
        const response = await fetch(route('cabinet-notifications.index', undefined, false), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            items.value = [];
            return;
        }
        const data = await response.json();
        items.value = data.notifications ?? [];
    } finally {
        loading.value = false;
    }
}

/** Сбрасывает непрочитанные на сервере и обновляет бейджи (без перезагрузки страницы). */
async function markAllReadQuiet() {
    await fetch(route('cabinet-notifications.read-all', undefined, false), {
        method: 'POST',
        headers: csrfHeaders(),
        credentials: 'same-origin',
    });
    await pollSummary();
    await fetchList();
}

async function pollSummary() {
    if (!authUser.value) {
        return;
    }
    try {
        const response = await fetch(route('cabinet-notifications.summary', undefined, false), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const data = await response.json();
        const badges = data.badges ?? {
            total: data.unread_count ?? 0,
            orders: 0,
            tasks: 0,
        };
        localBadges.value = badges;
        emit('badges', badges);

        const total = badges.total ?? 0;
        if (total > lastPolledTotal && lastPolledTotal > 0 && data.latest) {
            toast.value = data.latest;
            window.setTimeout(() => {
                toast.value = null;
            }, 6500);
        }
        lastPolledTotal = total;
    } catch {
        /* ignore */
    }
}

function toggle() {
    open.value = !open.value;
    if (open.value) {
        fetchList();
    }
}

async function markRead(id) {
    await fetch(route('cabinet-notifications.read', id, false), {
        method: 'POST',
        headers: csrfHeaders(),
        credentials: 'same-origin',
    });
    await fetchList();
    await pollSummary();
}

async function markAllRead() {
    await markAllReadQuiet();
    open.value = false;
    router.reload({ preserveScroll: true });
}

async function visit(item) {
    open.value = false;
    await markRead(item.id);
    router.visit(normalizeActionUrl(item.action_url));
}

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d);
}

onMounted(() => {
    const start = initialBadges();
    localBadges.value = start;
    emit('badges', start);
    lastPolledTotal = start.total ?? 0;
    pollTimer = window.setInterval(pollSummary, 45000);
    pollSummary();
});

onUnmounted(() => {
    if (pollTimer) {
        window.clearInterval(pollTimer);
    }
});

watch(
    () => page.props.cabinet_notification_badges,
    (next) => {
        if (next) {
            localBadges.value = next;
            emit('badges', next);
            lastPolledTotal = next.total ?? 0;
        }
    },
    { deep: true },
);

watch(authUser, (u) => {
    if (!u) {
        const empty = { total: 0, orders: 0, tasks: 0 };
        localBadges.value = empty;
        emit('badges', empty);
    }
});
</script>

<template>
    <div v-if="authUser" class="relative">
        <button
            type="button"
            :class="[
                'relative flex items-center justify-center border border-zinc-200 text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100',
                props.large ? 'h-11 w-11 shrink-0 rounded-2xl' : 'h-9 w-9 rounded-xl',
            ]"
            :aria-expanded="open"
            aria-label="Уведомления"
            @click="toggle"
        >
            <Bell :class="props.large ? 'h-5 w-5' : 'h-4 w-4'" />
            <span
                v-if="(localBadges.total ?? 0) > 0"
                class="absolute -right-0.5 -top-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold leading-none text-white"
            >
                {{ (localBadges.total ?? 0) > 99 ? '99+' : localBadges.total }}
            </span>
        </button>

        <div
            v-if="open"
            :class="[
                'absolute right-0 z-[70] w-[min(100vw-2rem,22rem)] rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900',
                props.large ? 'bottom-full mb-2' : 'mt-2',
            ]"
        >
            <div class="flex items-center justify-between border-b border-zinc-100 px-3 py-2 dark:border-zinc-800">
                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Уведомления</div>
                <button
                    type="button"
                    class="text-xs font-medium text-emerald-700 hover:underline dark:text-emerald-400"
                    @click="markAllRead"
                >
                    Прочитать все
                </button>
            </div>
            <div class="max-h-80 overflow-y-auto">
                <div v-if="loading" class="px-3 py-6 text-center text-xs text-zinc-500">Загрузка…</div>
                <div v-else-if="items.length === 0" class="px-3 py-6 text-center text-xs text-zinc-500">Пока пусто</div>
                <template v-else>
                    <button
                        v-for="item in items"
                        :key="item.id"
                        type="button"
                        class="flex w-full flex-col gap-1 border-b border-zinc-50 px-3 py-2.5 text-left last:border-0 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/80"
                        @click="visit(item)"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-50">{{ item.title }}</span>
                            <span v-if="!item.read_at" class="shrink-0 rounded-full bg-emerald-500/15 px-1.5 py-0.5 text-[10px] font-medium text-emerald-800 dark:text-emerald-300">новое</span>
                        </div>
                        <p class="text-xs leading-snug text-zinc-600 dark:text-zinc-400">{{ item.body }}</p>
                        <span class="text-[10px] text-zinc-400">{{ formatTime(item.created_at) }}</span>
                    </button>
                </template>
            </div>
        </div>

        <div
            v-if="open"
            class="fixed inset-0 z-[65] bg-transparent"
            aria-hidden="true"
            @click="open = false"
        />

        <Teleport to="body">
            <div
                v-if="toast"
                class="fixed bottom-28 left-1/2 z-[80] w-[min(100vw-2rem,24rem)] max-w-[calc(100vw-2rem)] -translate-x-1/2 rounded-2xl border border-zinc-200 bg-white p-4 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900 md:bottom-10"
                role="status"
            >
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Новое в кабинете</div>
                <div class="mt-1 text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ toast.title }}</div>
                <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">{{ toast.body }}</p>
            </div>
        </Teleport>
    </div>
</template>
