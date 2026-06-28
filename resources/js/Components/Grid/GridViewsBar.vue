<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Bookmark, ChevronDown, Pin, RotateCcw, Save, Share2, Star, Trash2 } from 'lucide-vue-next';
import GridViewSaveModal from '@/Components/Grid/GridViewSaveModal.vue';
import {
    captureGridStateFromApi,
    createGridView,
    deleteGridView,
    fetchGridView,
    fetchGridViews,
    persistViewToLocalStorage,
    readViewIdFromUrl,
    updateGridView,
    writeViewIdToUrl,
} from '@/support/gridViews.js';
import { crmGridDropdown, crmGridToolbarBtn } from '@/support/crmUi.js';

const props = defineProps({
    gridKey: { type: String, required: true },
    userId: { type: [String, Number], required: true },
    getGridApi: { type: Function, default: null },
    columnStorageKey: { type: String, default: null },
    filterStorageKey: { type: String, default: null },
    quickSearchStorageKey: { type: String, default: null },
    quickSearchJsonWrapper: { type: Boolean, default: false },
    quickSearch: { type: String, default: '' },
    onResetDefaults: { type: Function, default: null },
});

const emit = defineEmits(['update:quickSearch', 'applied', 'pinned-changed']);

const views = ref([]);
const canShare = ref(false);
const shareOptions = ref({ roles: [], users: [] });
const activeViewId = ref(null);
const menuOpen = ref(false);
const notice = ref('');
const loading = ref(false);
const saveModalOpen = ref(false);
const saveModalMode = ref('new');
const saveModalSaving = ref(false);

const storageKeys = computed(() => ({
    columnStorageKey: props.columnStorageKey,
    filterStorageKey: props.filterStorageKey,
    quickSearchStorageKey: props.quickSearchStorageKey,
}));

const activeView = computed(() => views.value.find((view) => view.id === activeViewId.value) ?? null);

const saveModalInitial = computed(() => ({
    name: saveModalMode.value === 'new' ? '' : (activeView.value?.name ?? ''),
    visibility: activeView.value?.visibility ?? 'private',
    sharedWith: activeView.value?.shared_with ?? { role_ids: [], user_ids: [] },
}));

let noticeTimeout = null;

function showNotice(message) {
    notice.value = message;

    if (noticeTimeout) {
        clearTimeout(noticeTimeout);
    }

    noticeTimeout = setTimeout(() => {
        notice.value = '';
        noticeTimeout = null;
    }, 2500);
}

async function reloadViews() {
    loading.value = true;

    try {
        const data = await fetchGridViews(props.gridKey);
        views.value = data.views ?? [];
        canShare.value = Boolean(data.can_share);
        shareOptions.value = data.share_options ?? { roles: [], users: [] };
    } finally {
        loading.value = false;
    }
}

function captureCurrentPayload() {
    const gridApi = props.getGridApi?.();
    const captured = captureGridStateFromApi(gridApi);

    return {
        column_state: captured.column_state,
        filter_state: captured.filter_state,
        quick_search: props.quickSearch ?? '',
    };
}

function applyViewToGrid(view) {
    persistViewToLocalStorage(view, storageKeys.value, {
        quickSearchJsonWrapper: props.quickSearchJsonWrapper,
    });

    const gridApi = props.getGridApi?.();

    if (gridApi) {
        if (Array.isArray(view.column_state) && view.column_state.length > 0) {
            gridApi.applyColumnState({ state: view.column_state, applyOrder: true });
        }

        gridApi.setFilterModel(view.filter_state ?? {});
    }

    emit('update:quickSearch', view.quick_search ?? '');
    activeViewId.value = view.id;
    writeViewIdToUrl(view.id);
    emit('applied', view);
}

async function applyViewById(viewId) {
    let view = views.value.find((item) => item.id === viewId) ?? null;

    if (!view) {
        view = await fetchGridView(viewId);
    }

    if (!view) {
        showNotice('Представление не найдено');

        return;
    }

    applyViewToGrid(view);
    menuOpen.value = false;
    showNotice(`Применено: ${view.name}`);
}

function openSaveModal(mode) {
    saveModalMode.value = mode;
    saveModalOpen.value = true;
}

async function handleSaveModalSubmit(formPayload) {
    saveModalSaving.value = true;

    try {
        const gridPayload = captureCurrentPayload();
        const payload = { ...gridPayload, ...formPayload };

        if (saveModalMode.value === 'update' && activeView.value?.can_manage) {
            const updated = await updateGridView(activeView.value.id, payload);
            views.value = views.value.map((view) => (view.id === updated.id ? updated : view));
            showNotice('Представление сохранено');
        } else if (saveModalMode.value === 'share' && activeView.value?.can_manage) {
            const updated = await updateGridView(activeView.value.id, {
                visibility: formPayload.visibility,
                shared_with: formPayload.shared_with,
            });
            views.value = views.value.map((view) => (view.id === updated.id ? updated : view));
            showNotice('Настройки доступа сохранены');
        } else {
            const created = await createGridView({
                grid_key: props.gridKey,
                ...payload,
            });
            views.value = [...views.value, created].sort((left, right) => left.name.localeCompare(right.name, 'ru'));
            activeViewId.value = created.id;
            writeViewIdToUrl(created.id);
            showNotice('Представление создано');
        }

        saveModalOpen.value = false;
    } catch {
        showNotice('Не удалось сохранить');
    } finally {
        saveModalSaving.value = false;
    }
}

async function saveCurrent() {
    if (activeView.value?.can_manage) {
        openSaveModal('update');

        return;
    }

    openSaveModal('new');
}

async function togglePin(view) {
    if (!view?.can_manage) {
        return;
    }

    const updated = await updateGridView(view.id, {
        is_pinned_sidebar: !view.is_pinned_sidebar,
    });

    views.value = views.value.map((item) => (item.id === updated.id ? updated : item));
    emit('pinned-changed');
    showNotice(updated.is_pinned_sidebar ? 'Закреплено в меню' : 'Убрано из избранного');
}

async function removeView(view) {
    if (!view?.can_manage) {
        return;
    }

    if (!window.confirm(`Удалить представление «${view.name}»?`)) {
        return;
    }

    const ok = await deleteGridView(view.id);

    if (!ok) {
        showNotice('Не удалось удалить');

        return;
    }

    views.value = views.value.filter((item) => item.id !== view.id);

    if (activeViewId.value === view.id) {
        activeViewId.value = null;
        writeViewIdToUrl(null);
    }

    emit('pinned-changed');
    showNotice('Удалено');
}

function resetDefaults() {
    props.onResetDefaults?.();
    activeViewId.value = null;
    writeViewIdToUrl(null);
    showNotice('Сброшено к пресету роли');
}

async function bootstrapFromUrl() {
    const viewId = readViewIdFromUrl();

    if (!viewId) {
        return;
    }

    await applyViewById(viewId);
}

onMounted(async () => {
    await reloadViews();
    await bootstrapFromUrl();
});

watch(
    () => props.gridKey,
    async () => {
        activeViewId.value = null;
        await reloadViews();
        await bootstrapFromUrl();
    },
);
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative">
            <button
                type="button"
                :class="`${crmGridToolbarBtn} gap-1.5`"
                :disabled="loading"
                @click="menuOpen = !menuOpen"
            >
                <Bookmark class="h-4 w-4 shrink-0" />
                <span class="max-w-[10rem] truncate">{{ activeView?.name ?? 'Представления' }}</span>
                <ChevronDown class="h-3.5 w-3.5 shrink-0 opacity-60" />
            </button>

            <div
                v-if="menuOpen"
                :class="`${crmGridDropdown} absolute left-0 top-full z-20 mt-1 min-w-[14rem] p-1`"
            >
                <button
                    type="button"
                    class="flex w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                    @click="resetDefaults(); menuOpen = false"
                >
                    По умолчанию (роль)
                </button>

                <div v-if="views.length === 0" class="px-3 py-2 text-xs text-zinc-500">
                    Сохранённых представлений пока нет
                </div>

                <button
                    v-for="view in views"
                    :key="view.id"
                    type="button"
                    class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                    :class="activeViewId === view.id ? 'bg-zinc-100 font-medium dark:bg-zinc-800' : ''"
                    @click="applyViewById(view.id)"
                >
                    <span class="truncate">{{ view.name }}</span>
                    <Star v-if="view.is_pinned_sidebar" class="h-3.5 w-3.5 shrink-0 text-amber-500" />
                </button>
            </div>
        </div>

        <button type="button" :class="crmGridToolbarBtn" title="Сохранить" @click="saveCurrent">
            <Save class="h-4 w-4" />
        </button>

        <button type="button" :class="crmGridToolbarBtn" title="Сохранить как…" @click="openSaveModal('new')">
            <span class="text-xs font-medium">+</span>
        </button>

        <button
            v-if="activeView?.can_manage && canShare"
            type="button"
            :class="crmGridToolbarBtn"
            title="Поделиться"
            @click="openSaveModal('share')"
        >
            <Share2 class="h-4 w-4" />
        </button>

        <button
            v-if="activeView?.can_manage"
            type="button"
            :class="crmGridToolbarBtn"
            :title="activeView.is_pinned_sidebar ? 'Убрать из избранного' : 'В избранное'"
            @click="togglePin(activeView)"
        >
            <Pin class="h-4 w-4" :class="activeView.is_pinned_sidebar ? 'text-amber-500' : ''" />
        </button>

        <button
            v-if="activeView?.can_manage"
            type="button"
            :class="crmGridToolbarBtn"
            title="Удалить представление"
            @click="removeView(activeView)"
        >
            <Trash2 class="h-4 w-4 text-rose-600 dark:text-rose-400" />
        </button>

        <button type="button" :class="crmGridToolbarBtn" title="Сбросить" @click="resetDefaults">
            <RotateCcw class="h-4 w-4" />
        </button>

        <span v-if="notice" class="text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ notice }}</span>
    </div>

    <GridViewSaveModal
        :show="saveModalOpen"
        :mode="saveModalMode"
        :initial-name="saveModalInitial.name"
        :initial-visibility="saveModalInitial.visibility"
        :initial-shared-with="saveModalInitial.sharedWith"
        :can-share="canShare"
        :share-options="shareOptions"
        :saving="saveModalSaving"
        @close="saveModalOpen = false"
        @submit="handleSaveModalSubmit"
    />

    <div v-if="menuOpen" class="fixed inset-0 z-10" @click="menuOpen = false" />
</template>
