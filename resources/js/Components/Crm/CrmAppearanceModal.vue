<script setup>
import { computed, reactive, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import {
    applyCrmAppearanceToDocument,
    resolveCrmAppearance,
    schedulePersistCrmAppearance,
    writeLocalCrmAppearance,
} from '@/support/crmAppearance.js';
import { writeLocalAgGridDensity } from '@/support/agGridUserDensity.js';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);

const form = reactive({
    workspace_skin: 'classic',
    button_radius: 'sharp',
    primary_accent: 'emerald',
    tab_style: 'filled',
});

function syncFormFromUser() {
    const resolved = resolveCrmAppearance(authUser.value);
    form.workspace_skin = resolved.workspace_skin;
    form.button_radius = resolved.button_radius;
    form.primary_accent = resolved.primary_accent;
    form.tab_style = resolved.tab_style;
}

watch(() => props.show, (visible) => {
    if (visible) {
        syncFormFromUser();
    }
});

function preview() {
    writeLocalCrmAppearance(form);
    applyCrmAppearanceToDocument(form);
}

function save() {
    writeLocalCrmAppearance(form);
    applyCrmAppearanceToDocument(form);

    const density = authUser.value?.ui_preferences?.ag_grid_density ?? 'normal';
    schedulePersistCrmAppearance({
        workspace_skin: form.workspace_skin,
        button_radius: form.button_radius,
        primary_accent: form.primary_accent,
        tab_style: form.tab_style,
        ag_grid_density: density,
    });

    emit('close');
}

const radiusOptions = [
    { value: 'rounded', label: 'Скруглённые', hint: 'Кнопки и поля с мягкими углами' },
    { value: 'sharp', label: 'Прямые углы', hint: 'Строгий вид, как в таблицах' },
];

const accentOptions = [
    { value: 'emerald', label: 'Зелёные', sampleClass: 'border-emerald-200/90 bg-emerald-50 text-emerald-900 dark:border-emerald-800/70 dark:bg-emerald-950/50 dark:text-emerald-50' },
    { value: 'sky', label: 'Голубые', sampleClass: 'border-sky-200/90 bg-sky-50 text-sky-900 dark:border-sky-800/70 dark:bg-sky-950/50 dark:text-sky-50' },
];

const tabOptions = [
    { value: 'filled', label: 'Заливка' },
    { value: 'underline', label: 'Подчёркивание' },
];

const workspaceSkinOptions = [
    {
        value: 'classic',
        label: 'Классический',
        hint: 'Текущий вид CRM: zinc, таблицы, как сейчас у всех страниц.',
    },
    {
        value: 'sky',
        label: 'Sky',
        hint: 'Мягкие карточки и голубой акцент, как в модуле «Сколько влезет».',
    },
];
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <section class="bg-white dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Внешний вид</h2>
            </div>

            <div class="space-y-6 px-5 py-5">
                <div class="space-y-2">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Общий вид интерфейса</div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Можно вернуться к классическому оформлению в любой момент — настройка личная, для каждого пользователя.
                    </p>
                    <div class="grid gap-2">
                        <button
                            v-for="option in workspaceSkinOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-left text-sm transition"
                            :class="form.workspace_skin === option.value
                                ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-800'
                                : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800'"
                            @click="form.workspace_skin = option.value; preview()"
                        >
                            <div class="font-medium">{{ option.label }}</div>
                            <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ option.hint }}</div>
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Углы элементов</div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="option in radiusOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-left text-sm transition"
                            :class="form.button_radius === option.value
                                ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-800'
                                : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800'"
                            @click="form.button_radius = option.value; preview()"
                        >
                            <div class="font-medium">{{ option.label }}</div>
                            <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ option.hint }}</div>
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Цвет кнопок «Сохранить» и «Добавить»</div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="option in accentOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-left transition"
                            :class="form.primary_accent === option.value
                                ? 'border-zinc-900 ring-1 ring-zinc-900 dark:border-zinc-100 dark:ring-zinc-100'
                                : 'border-zinc-200 dark:border-zinc-700'"
                            @click="form.primary_accent = option.value; preview()"
                        >
                            <span
                                class="inline-flex items-center gap-2 border px-3 py-1.5 text-sm font-medium"
                                :class="[option.sampleClass, form.button_radius === 'rounded' ? 'rounded-lg' : 'rounded-none']"
                            >
                                Пример
                            </span>
                            <div class="mt-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ option.label }}</div>
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Вкладки в карточках</div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in tabOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-xl border px-4 py-2 text-sm transition"
                            :class="form.tab_style === option.value
                                ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900'
                                : 'border-zinc-200 text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                            @click="form.tab_style = option.value; preview()"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1 border border-zinc-200 bg-zinc-50/80 p-2 dark:border-zinc-700 dark:bg-zinc-900/50">
                        <span
                            class="crm-tab-btn crm-tab-btn--active-filled"
                            :data-preview-tab="form.tab_style"
                        >Основное</span>
                        <span class="crm-tab-btn crm-tab-btn--inactive">Маршрут</span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                <button
                    type="button"
                    class="crm-btn-neutral"
                    @click="emit('close')"
                >
                    Отмена
                </button>
                <button
                    type="button"
                    class="crm-btn-create"
                    @click="save"
                >
                    Сохранить
                </button>
            </div>
        </section>
    </Modal>
</template>
