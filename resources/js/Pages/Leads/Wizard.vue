<template>
    <div class="flex h-full min-h-0 flex-col border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center border border-rose-200 bg-rose-50 text-rose-600 transition-colors hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-950/60"
                    title="К реестру"
                    @click="goBack"
                >
                    <X class="h-5 w-5" />
                    <span class="sr-only">К реестру</span>
                </button>
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-semibold">{{ selectedLeadId ? form.number || 'Лид' : 'Новый лид' }}</h1>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="secondary-button" :disabled="!selectedLeadId" @click="prepareProposal"><FileText class="h-4 w-4" />Сформировать коммерческое</button>
                <button type="button" class="primary-button" :disabled="!selectedLeadId || !form.counterparty_id" @click="convertLead"><ArrowRightLeft class="h-4 w-4" />Конвертировать в заказ</button>
                <button type="button" class="secondary-button" @click="submit"><Save class="h-4 w-4" />Сохранить</button>
            </div>
        </div>

        <div class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-800">
            <div class="flex flex-wrap gap-2">
                <button v-for="tab in tabs" :key="tab.key" type="button" class="inline-flex items-center gap-2 border px-3 py-2 text-sm transition-colors" :class="activeTab === tab.key ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900' : 'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800'" @click="activeTab = tab.key">
                    <component :is="tab.icon" class="h-4 w-4" />
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
            <div v-if="activeTab === 'main'" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-2"><label class="label">Статус</label><select v-model="form.status" class="field"><option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
                    <div class="space-y-2"><label class="label">Источник</label><select v-model="form.source" class="field"><option value="">Не выбрано</option><option v-for="option in sourceOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
                    <div class="space-y-2"><label class="label">Ответственный</label><select v-model="form.responsible_id" class="field"><option v-for="user in responsibleUsers" :key="user.id" :value="user.id">{{ user.name }}</option></select></div>
                    <div class="space-y-2"><label class="label">Плановая отгрузка</label><input v-model="form.planned_shipping_date" type="date" class="field" /></div>
                </div>
                <div class="space-y-2"><label class="label">Тема лида</label><input v-model="form.title" type="text" class="field" /></div>
                <div class="space-y-2"><label class="label">Описание</label><textarea v-model="form.description" rows="4" class="field" /></div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-2"><label class="label">Контрагент</label><select v-model="form.counterparty_id" class="field"><option :value="null">Не выбран</option><option v-for="contractor in contractors" :key="contractor.id" :value="contractor.id">{{ contractor.name }}</option></select></div>
                    <div class="space-y-2"><label class="label">Тип перевозки</label><select v-model="form.transport_type" class="field"><option value="">Не выбрано</option><option v-for="option in transportTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
                    <div class="space-y-2"><label class="label">Цена клиента</label><input v-model="form.target_price" type="number" min="0" step="0.01" class="field" /></div>
                    <div class="space-y-2"><label class="label">Валюта</label><select v-model="form.target_currency" class="field"><option v-for="option in currencyOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
                </div>
                <div class="grid gap-4 xl:grid-cols-4">
                    <div class="space-y-2"><label class="label">Потребность</label><input v-model="form.qualification.need" type="text" class="field" /></div>
                    <div class="space-y-2"><label class="label">Срок</label><input v-model="form.qualification.timeline" type="text" class="field" /></div>
                    <div class="space-y-2"><label class="label">ЛПР</label><input v-model="form.qualification.authority" type="text" class="field" /></div>
                    <div class="space-y-2"><label class="label">Бюджет</label><input v-model="form.qualification.budget" type="text" class="field" /></div>
                </div>
            </div>

            <div v-else-if="activeTab === 'route'" class="space-y-4">
                <div class="flex items-center justify-between gap-3"><div><h3 class="text-base font-semibold">Маршрут</h3><p class="text-sm text-zinc-500 dark:text-zinc-400">Точки до конверсии в заказ.</p></div><button type="button" class="secondary-button" @click="addRoutePoint('loading')"><Plus class="h-4 w-4" />Погрузка</button></div>
                <div v-for="(point, index) in form.route_points" :key="`point-${index}`" class="grid gap-3 border border-zinc-200 p-4 dark:border-zinc-800 xl:grid-cols-[140px,1fr,170px,170px,170px,44px]">
                    <select v-model="point.type" class="field"><option value="loading">Погрузка</option><option value="unloading">Выгрузка</option></select>
                    <input v-model="point.address" type="text" class="field" placeholder="Адрес" />
                    <input v-model="point.planned_date" type="date" class="field" />
                    <input v-model="point.contact_person" type="text" class="field" placeholder="Контакт" />
                    <input v-model="point.contact_phone" type="text" class="field" placeholder="Телефон" />
                    <button type="button" class="icon-danger" @click="removeRoutePoint(index)"><Trash2 class="h-4 w-4" /></button>
                </div>
            </div>

            <div v-else-if="activeTab === 'cargo'" class="space-y-4">
                <div class="flex items-center justify-between gap-3"><div><h3 class="text-base font-semibold">Груз</h3><p class="text-sm text-zinc-500 dark:text-zinc-400">Позиции груза для просчёта.</p></div><button type="button" class="secondary-button" @click="addCargoItem"><Plus class="h-4 w-4" />Добавить груз</button></div>
                <div v-for="(cargo, index) in form.cargo_items" :key="`cargo-${index}`" class="space-y-3 border border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <input v-model="cargo.name" type="text" class="field" placeholder="Наименование" />
                        <input v-model="cargo.weight_kg" type="number" min="0" step="0.01" class="field" placeholder="Вес, кг" />
                        <input v-model="cargo.volume_m3" type="number" min="0" step="0.01" class="field" placeholder="Объём, м3" />
                        <input v-model="cargo.package_count" type="number" min="0" step="1" class="field" placeholder="Кол-во мест" />
                    </div>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <select v-model="cargo.package_type" class="field"><option value="">Упаковка</option><option value="pallet">Паллета</option><option value="box">Короб</option><option value="crate">Ящик</option><option value="roll">Рулон</option><option value="bag">Мешок</option></select>
                        <select v-model="cargo.cargo_type" class="field"><option value="general">Общий</option><option value="dangerous">Опасный</option><option value="temperature_controlled">Температурный</option><option value="oversized">Негабарит</option><option value="fragile">Хрупкий</option></select>
                        <input v-model="cargo.hs_code" type="text" class="field" placeholder="Код ТН ВЭД" />
                        <label class="flex items-center gap-2 border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700"><input v-model="cargo.dangerous_goods" type="checkbox" class="h-4 w-4" />Опасный груз</label>
                    </div>
                    <div class="grid gap-3 xl:grid-cols-[1fr,180px,44px]">
                        <textarea v-model="cargo.description" rows="2" class="field" placeholder="Описание" />
                        <input v-model="cargo.dangerous_class" type="text" class="field" placeholder="Класс опасности" />
                        <button type="button" class="icon-danger" @click="removeCargoItem(index)"><Trash2 class="h-4 w-4" /></button>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'activities'" class="space-y-4">
                <div class="flex items-center justify-between gap-3"><div><h3 class="text-base font-semibold">Коммуникации</h3><p class="text-sm text-zinc-500 dark:text-zinc-400">История контактов по лиду.</p></div><button type="button" class="secondary-button" @click="addActivity"><Plus class="h-4 w-4" />Добавить активность</button></div>
                <div v-for="(activity, index) in form.activities" :key="`activity-${index}`" class="space-y-3 border border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <select v-model="activity.type" class="field"><option value="call">Звонок</option><option value="email">Email</option><option value="meeting">Встреча</option><option value="note">Заметка</option></select>
                        <input v-model="activity.subject" type="text" class="field" placeholder="Тема" />
                        <input v-model="activity.next_action_at" type="datetime-local" class="field" />
                        <button type="button" class="icon-danger" @click="removeActivity(index)"><Trash2 class="h-4 w-4" /></button>
                    </div>
                    <textarea v-model="activity.content" rows="3" class="field" placeholder="Комментарий" />
                </div>
            </div>

            <div v-else class="grid gap-4 xl:grid-cols-[1.4fr,0.9fr]">
                <div class="space-y-3 border border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="flex items-center justify-between gap-3"><div><h3 class="text-base font-semibold">Коммерческое предложение</h3><p class="text-sm text-zinc-500 dark:text-zinc-400">Печатную форму подключим следующим модулем.</p></div><button type="button" class="secondary-button" :disabled="!selectedLeadId" @click="prepareProposal"><FileText class="h-4 w-4" />Сформировать</button></div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div><div class="meta">Тема</div><div class="text-sm">{{ form.title || '—' }}</div></div>
                        <div><div class="meta">Цена</div><div class="text-sm">{{ form.target_price ? formatMoney(form.target_price, form.target_currency) : '—' }}</div></div>
                        <div><div class="meta">Маршрут</div><div class="text-sm">{{ form.loading_location || '—' }} → {{ form.unloading_location || '—' }}</div></div>
                        <div><div class="meta">Контрагент</div><div class="text-sm">{{ selectedCounterpartyName }}</div></div>
                    </div>
                </div>
                <div class="space-y-3 border border-zinc-200 p-4 dark:border-zinc-800">
                    <h3 class="text-base font-semibold">История КП и конверсии</h3>
                    <div v-for="offer in form.offers" :key="offer.id" class="border border-zinc-200 p-3 text-sm dark:border-zinc-800">
                        <div class="flex items-center justify-between gap-3"><div class="font-medium">{{ offer.number || 'Черновик КП' }}</div><span class="text-xs text-zinc-500 dark:text-zinc-400">{{ offer.offer_date || '—' }}</span></div>
                        <div class="mt-2 text-zinc-500 dark:text-zinc-400">{{ offer.price ? formatMoney(offer.price, offer.currency) : 'Без цены' }}</div>
                    </div>
                    <div v-if="form.offers.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">Коммерческие предложения ещё не формировались.</div>
                    <div v-if="form.orders.length" class="border border-zinc-200 p-3 text-sm dark:border-zinc-800"><div class="meta">Конвертирован в заказ</div><div class="mt-2 font-medium">{{ form.orders[0].order_number }}</div></div>
                </div>
            </div>
        </div>

        <div v-if="selectedLeadId" class="flex items-center justify-between gap-4 border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Удаление используется для чистки воронки.</div>
            <button type="button" class="danger-button" @click="destroyLead"><Trash2 class="h-4 w-4" />Удалить</button>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { ArrowRightLeft, ClipboardList, FileText, History, MapPinned, Package, Plus, Save, Trash2, X } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({ layout: (h, page) => h(CrmLayout, { activeKey: 'leads' }, () => page) });

const props = defineProps({
    selectedLead: Object, isCreating: Boolean, contractors: Array, responsibleUsers: Array,
    statusOptions: Array, sourceOptions: Array, transportTypeOptions: Array, currencyOptions: Array,
});

const activeTab = ref('main');
const tabs = [
    { key: 'main', label: 'Основное', icon: ClipboardList },
    { key: 'route', label: 'Маршрут', icon: MapPinned },
    { key: 'cargo', label: 'Груз', icon: Package },
    { key: 'activities', label: 'Коммуникации', icon: History },
    { key: 'commercial', label: 'Коммерческое', icon: FileText },
];

function blankForm() {
    return { number: '', status: 'new', source: '', counterparty_id: null, responsible_id: props.responsibleUsers?.[0]?.id ?? null, title: '', description: '', transport_type: '', loading_location: '', unloading_location: '', planned_shipping_date: '', target_price: null, target_currency: 'RUB', calculated_cost: null, expected_margin: null, next_contact_at: '', lost_reason: '', qualification: { need: '', timeline: '', authority: '', budget: '' }, route_points: [], cargo_items: [], activities: [], offers: [], orders: [] };
}

function leadToForm(lead) {
    if (!lead) {
        return blankForm();
    }

    return { ...blankForm(), ...lead, qualification: { need: lead.qualification?.need ?? '', timeline: lead.qualification?.timeline ?? '', authority: lead.qualification?.authority ?? '', budget: lead.qualification?.budget ?? '' }, route_points: lead.route_points ?? [], cargo_items: lead.cargo_items ?? [], activities: lead.activities ?? [], offers: lead.offers ?? [], orders: lead.orders ?? [] };
}

const form = useForm(leadToForm(props.selectedLead));

watch(() => props.selectedLead, (lead) => {
    const payload = leadToForm(lead);
    form.defaults(payload);
    form.reset();
    Object.entries(payload).forEach(([key, value]) => { form[key] = value; });
    activeTab.value = 'main';
}, { immediate: true });

const selectedLeadId = computed(() => props.selectedLead?.id ?? null);
const selectedCounterpartyName = computed(() => props.contractors?.find((contractor) => contractor.id === form.counterparty_id)?.name ?? 'Не выбран');

function goBack() { router.get(route('leads.index')); }
function addRoutePoint(type = 'loading') { form.route_points.push({ type, sequence: form.route_points.length + 1, address: '', normalized_data: {}, planned_date: '', contact_person: '', contact_phone: '' }); }
function removeRoutePoint(index) { form.route_points.splice(index, 1); form.route_points = form.route_points.map((point, pointIndex) => ({ ...point, sequence: pointIndex + 1 })); }
function addCargoItem() { form.cargo_items.push({ name: '', description: '', weight_kg: null, volume_m3: null, package_type: '', package_count: null, dangerous_goods: false, dangerous_class: '', hs_code: '', cargo_type: 'general' }); }
function removeCargoItem(index) { form.cargo_items.splice(index, 1); }
function addActivity() { form.activities.push({ type: 'note', subject: '', content: '', next_action_at: '' }); }
function removeActivity(index) { form.activities.splice(index, 1); }

function submit() {
    const payload = { ...form.data(), offers: undefined, orders: undefined };

    if (selectedLeadId.value) {
        router.patch(route('leads.update', selectedLeadId.value), payload);
        return;
    }

    router.post(route('leads.store'), payload);
}

function prepareProposal() { if (selectedLeadId.value) router.post(route('leads.proposal', selectedLeadId.value)); }
function convertLead() { if (selectedLeadId.value) router.post(route('leads.convert', selectedLeadId.value), {}); }
function destroyLead() { if (selectedLeadId.value) router.delete(route('leads.destroy', selectedLeadId.value)); }
function formatMoney(value, currency = 'RUB') { return new Intl.NumberFormat('ru-RU', { style: 'currency', currency, maximumFractionDigits: 2 }).format(Number(value)); }
</script>

<style scoped>
.field { @apply w-full border border-zinc-200 bg-white px-3 py-2 text-sm outline-none transition-colors focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400; }
.label { @apply text-sm font-medium; }
.meta { @apply text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400; }
.secondary-button { @apply inline-flex items-center gap-2 border border-zinc-200 px-3 py-2 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800; }
.primary-button { @apply inline-flex items-center gap-2 border border-zinc-900 bg-zinc-900 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200; }
.danger-button { @apply inline-flex items-center gap-2 border border-rose-200 px-3 py-2 text-sm font-medium text-rose-700 transition-colors hover:bg-rose-50 dark:border-rose-900/50 dark:text-rose-300 dark:hover:bg-rose-950/40; }
.icon-danger { @apply inline-flex h-11 w-11 items-center justify-center border border-rose-200 text-rose-700 transition-colors hover:bg-rose-50 dark:border-rose-900/50 dark:text-rose-300 dark:hover:bg-rose-950/40; }
</style>
