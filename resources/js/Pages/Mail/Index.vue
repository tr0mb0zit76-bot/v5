<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4">
        <CrmPageHeader
            lead="Исходящие письма и переписка по лидам. Входящие (IMAP) — в следующих фазах."
            title="Почта"
        />

        <div class="grid gap-4 xl:grid-cols-[1fr,1.1fr]">
            <form :class="`${crmPanel} space-y-3 p-4`" @submit.prevent="submitSend">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Новое письмо</h2>
                <div>
                    <label :class="crmLabel">Лид (необязательно)</label>
                    <select v-model="sendForm.lead_id" :class="crmFieldFluid">
                        <option :value="null">Без привязки</option>
                        <option v-for="lead in leads" :key="lead.id" :value="lead.id">
                            {{ lead.number }} — {{ lead.title }}
                        </option>
                    </select>
                </div>
                <div>
                    <label :class="crmLabel">Кому (через запятую)</label>
                    <input v-model="sendForm.to_raw" type="text" :class="crmFieldFluid" placeholder="client@example.com" />
                </div>
                <div>
                    <label :class="crmLabel">Тема</label>
                    <input v-model="sendForm.subject" type="text" :class="crmFieldFluid" />
                </div>
                <div>
                    <label :class="crmLabel">Текст</label>
                    <textarea v-model="sendForm.body" rows="6" :class="crmFieldFluid" />
                </div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">От: {{ fromEmail }}</p>
                <button type="submit" :class="crmBtnPrimary" :disabled="sendForm.processing">Отправить</button>
            </form>

            <div :class="`${crmPanel} space-y-3 p-4`">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Недавние цепочки</h2>
                <div v-if="threads.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">Писем пока нет.</div>
                <div
                    v-for="thread in threads"
                    :key="thread.id"
                    class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-800"
                >
                    <div class="font-medium text-zinc-900 dark:text-zinc-50">{{ thread.subject }}</div>
                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        <span v-if="thread.lead_number">Лид {{ thread.lead_number }}</span>
                        <span v-if="thread.last_message_at"> · {{ formatWhen(thread.last_message_at) }}</span>
                    </div>
                    <p v-if="thread.preview" class="mt-2 line-clamp-2 text-zinc-600 dark:text-zinc-300">{{ thread.preview }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnPrimary, crmFieldFluid, crmLabel, crmPanel } from '@/support/crmUi.js';

defineOptions({ layout: (h, page) => h(CrmLayout, { activeKey: 'mail' }, () => page) });

const props = defineProps({
    threads: {
        type: Array,
        default: () => [],
    },
    leads: {
        type: Array,
        default: () => [],
    },
    fromEmail: {
        type: String,
        default: '',
    },
});

const sendForm = useForm({
    lead_id: null,
    to_raw: '',
    subject: '',
    body: '',
});

function submitSend() {
    const to = sendForm.to_raw
        .split(/[,;]/)
        .map((s) => s.trim())
        .filter(Boolean);

    sendForm
        .transform((data) => ({
            lead_id: data.lead_id,
            to,
            subject: data.subject,
            body: data.body,
        }))
        .post(route('mail.send'), {
            preserveScroll: true,
            onSuccess: () => {
                sendForm.reset('to_raw', 'subject', 'body');
            },
        });
}

function formatWhen(iso) {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleString('ru-RU');
}
</script>
