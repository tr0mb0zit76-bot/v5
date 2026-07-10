<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto lg:min-h-0">
        <CrmPageHeader
            lead="Установка, включение и порядок модулей платформы."
            title="Менеджер модулей"
            :title-class="crmPageTitleSm"
        >
            <template #actions>
                <button type="button" :class="crmBtnCreate" @click="showInstallModal = true">
                    <Plus class="h-4 w-4" />
                    Установить модуль
                </button>
            </template>
        </CrmPageHeader>

        <section :class="`${crmPanel} overflow-hidden`">
            <div v-if="loading" class="py-12 text-center">
                <Loader2 class="mx-auto h-8 w-8 animate-spin text-zinc-400" />
                <p :class="`${crmPageLead} mt-3`">Загрузка модулей…</p>
            </div>

            <div v-else-if="modules.length === 0" class="py-12 text-center">
                <Package class="mx-auto h-12 w-12 text-zinc-400" />
                <h3 class="mt-3 text-sm font-semibold">Модули не установлены</h3>
                <p :class="`${crmPageLead} mt-1`">Начните с установки нового модуля.</p>
                <button type="button" :class="`${crmBtnCreate} mt-6`" @click="showInstallModal = true">
                    <Plus class="h-4 w-4" />
                    Установить модуль
                </button>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="bg-zinc-100 dark:bg-zinc-800">
                        <tr class="text-left text-zinc-600 dark:text-zinc-200">
                            <th class="border-b border-zinc-200 px-4 py-3 font-medium dark:border-zinc-700">Модуль</th>
                            <th class="border-b border-zinc-200 px-4 py-3 font-medium dark:border-zinc-700">Версия</th>
                            <th class="border-b border-zinc-200 px-4 py-3 font-medium dark:border-zinc-700">Статус</th>
                            <th class="border-b border-zinc-200 px-4 py-3 font-medium dark:border-zinc-700">Порядок</th>
                            <th class="border-b border-zinc-200 px-4 py-3 text-right font-medium dark:border-zinc-700">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="module in modules"
                            :key="module.id"
                            class="border-b border-zinc-100 dark:border-zinc-800"
                            :class="{ 'opacity-70': !module.enabled }"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
                                        <Package class="h-5 w-5 text-zinc-500" />
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ module.name }}</div>
                                        <div :class="crmPageLead">{{ module.slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ module.version }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="module.enabled
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'
                                        : 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-200'"
                                >
                                    {{ module.enabled ? 'Включён' : 'Выключен' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button
                                        v-if="module.order > 1"
                                        type="button"
                                        :class="`${crmBtnNeutral} !px-2 !py-1`"
                                        @click="moveModule(module, 'up')"
                                    >
                                        ↑
                                    </button>
                                    <span>{{ module.order }}</span>
                                    <button
                                        v-if="module.order < modules.length"
                                        type="button"
                                        :class="`${crmBtnNeutral} !px-2 !py-1`"
                                        @click="moveModule(module, 'down')"
                                    >
                                        ↓
                                    </button>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        :class="crmBtnSecondary"
                                        :disabled="toggling === module.id"
                                        @click="toggleModule(module)"
                                    >
                                        <Loader2 v-if="toggling === module.id" class="h-4 w-4 animate-spin" />
                                        <span v-else>{{ module.enabled ? 'Выключить' : 'Включить' }}</span>
                                    </button>
                                    <button
                                        v-if="module.name !== 'Core' && module.name !== 'ModuleManager'"
                                        type="button"
                                        :class="crmBtnDangerMuted"
                                        :disabled="deleting === module.id"
                                        @click="confirmUninstall(module)"
                                    >
                                        <Loader2 v-if="deleting === module.id" class="h-4 w-4 animate-spin" />
                                        <span v-else>Удалить</span>
                                    </button>
                                    <span v-else :class="crmPageLead">Защищён</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Modal :show="showInstallModal" max-width="lg" @close="showInstallModal = false">
            <section :class="crmModalFormShell">
                <CrmModalHeader title="Установить модуль" @close="showInstallModal = false" />
                <form :class="`${crmModalFormBody} space-y-4 px-6 py-5`" @submit.prevent="installModule">
                    <div :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                        <label :class="crmModalFieldLabel" for="module-name">Название</label>
                        <input
                            id="module-name"
                            v-model="newModuleName"
                            type="text"
                            :class="crmFieldFluid"
                            placeholder="Order, Contractor, Finance…"
                            @keyup.enter="installModule"
                        />
                    </div>
                    <p v-if="installError" class="text-sm text-rose-600 dark:text-rose-400">{{ installError }}</p>
                    <div class="flex justify-end gap-2">
                        <button type="button" :class="crmBtnSecondary" @click="showInstallModal = false">Отмена</button>
                        <button type="submit" :class="crmBtnPrimary" :disabled="!newModuleName.trim() || installing">
                            <Loader2 v-if="installing" class="h-4 w-4 animate-spin" />
                            {{ installing ? 'Установка…' : 'Установить' }}
                        </button>
                    </div>
                </form>
            </section>
        </Modal>

        <Modal :show="showUninstallModal" max-width="lg" @close="showUninstallModal = false">
            <section :class="crmModalFormShell">
                <CrmModalHeader title="Удалить модуль" @close="showUninstallModal = false" />
                <div :class="`${crmModalFormBody} space-y-4 px-6 py-5`">
                    <p class="text-sm text-zinc-700 dark:text-zinc-200">
                        Удалить модуль <strong>{{ moduleToUninstall?.name }}</strong>?
                        Действие необратимо: будут удалены файлы и данные модуля.
                    </p>
                    <div class="flex justify-end gap-2">
                        <button type="button" :class="crmBtnSecondary" @click="showUninstallModal = false">Отмена</button>
                        <button type="button" :class="crmBtnDangerMuted" @click="uninstallModule">Удалить</button>
                    </div>
                </div>
            </section>
        </Modal>

        <div
            v-if="toast.show"
            class="fixed bottom-4 right-4 z-50 max-w-sm rounded-xl border p-4 shadow-lg"
            :class="toast.type === 'success'
                ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100'
                : 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100'"
        >
            <div class="flex items-start gap-3">
                <p class="text-sm font-medium">{{ toast.message }}</p>
                <button type="button" class="ml-auto text-zinc-500 hover:text-zinc-700" @click="toast.show = false">×</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { Loader2, Package, Plus } from 'lucide-vue-next';
import CrmModalHeader from '@/Components/Crm/CrmModalHeader.vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import Modal from '@/Components/Modal.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmBtnCreate,
    crmBtnDangerMuted,
    crmBtnNeutral,
    crmBtnPrimary,
    crmBtnSecondary,
    crmFieldFluid,
    crmLabel,
    crmModalFieldLabel,
    crmModalFieldRow,
    crmModalFormBody,
    crmModalFormShell,
    crmPageLead,
    crmPageTitleSm,
    crmPanel,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings' }, () => page),
});

const modules = ref([]);
const loading = ref(true);
const toggling = ref(null);
const deleting = ref(null);
const installing = ref(false);
const showInstallModal = ref(false);
const showUninstallModal = ref(false);
const newModuleName = ref('');
const moduleToUninstall = ref(null);
const installError = ref('');
const toast = ref({
    show: false,
    message: '',
    type: 'success',
});

async function fetchModules() {
    loading.value = true;

    try {
        const response = await axios.get('/api/modules');
        modules.value = response.data;
    } catch (error) {
        console.error('Error fetching modules:', error);
        showToast('Не удалось загрузить модули', 'error');
    } finally {
        loading.value = false;
    }
}

async function toggleModule(module) {
    toggling.value = module.id;

    try {
        const response = await axios.post(`/api/modules/${module.id}/toggle`);
        await fetchModules();
        showToast(response.data.message, 'success');
    } catch (error) {
        console.error('Error toggling module:', error);
        showToast('Не удалось изменить статус модуля', 'error');
    } finally {
        toggling.value = null;
    }
}

async function installModule() {
    if (!newModuleName.value.trim()) {
        return;
    }

    installing.value = true;
    installError.value = '';

    try {
        const response = await axios.post('/api/modules/install', {
            name: newModuleName.value.trim(),
        });

        showInstallModal.value = false;
        newModuleName.value = '';
        await fetchModules();
        showToast(response.data.message, 'success');
    } catch (error) {
        console.error('Error installing module:', error);
        installError.value = error.response?.data?.error || 'Не удалось установить модуль';
    } finally {
        installing.value = false;
    }
}

function confirmUninstall(module) {
    moduleToUninstall.value = module;
    showUninstallModal.value = true;
}

async function uninstallModule() {
    if (!moduleToUninstall.value) {
        return;
    }

    deleting.value = moduleToUninstall.value.id;

    try {
        await axios.delete(`/api/modules/${moduleToUninstall.value.id}`);
        showUninstallModal.value = false;
        await fetchModules();
        showToast(`Модуль ${moduleToUninstall.value.name} удалён`, 'success');
        moduleToUninstall.value = null;
    } catch (error) {
        console.error('Error uninstalling module:', error);
        showToast('Не удалось удалить модуль', 'error');
    } finally {
        deleting.value = null;
    }
}

async function moveModule(module, direction) {
    const currentIndex = modules.value.findIndex((row) => row.id === module.id);
    const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;

    if (targetIndex < 0 || targetIndex >= modules.value.length) {
        return;
    }

    const targetModule = modules.value[targetIndex];
    const currentOrder = module.order;
    const targetOrder = targetModule.order;

    try {
        await axios.put(`/api/modules/${module.id}`, { order: targetOrder });
        await axios.put(`/api/modules/${targetModule.id}`, { order: currentOrder });
        await fetchModules();
        showToast('Порядок модулей обновлён', 'success');
    } catch (error) {
        console.error('Error moving module:', error);
        showToast('Не удалось изменить порядок', 'error');
    }
}

function showToast(message, type = 'success') {
    toast.value = {
        show: true,
        message,
        type,
    };

    window.setTimeout(() => {
        toast.value.show = false;
    }, 3000);
}

onMounted(() => {
    fetchModules();
});
</script>
