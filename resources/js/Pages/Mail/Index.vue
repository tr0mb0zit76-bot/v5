<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Почта</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Исходящие письма и переписка по лидам. Входящие (IMAP) — в следующих фазах.
            </p>
        </div>

        <div class="grid gap-4 xl:grid-cols-[1fr,1.1fr]">
            <form class="space-y-3 border border-zinc-200 p-4 dark:border-zinc-800" @submit.prevent="submitSend">
                <h2 class="text-base font-semibold">Новое письмо</h2>
                <div>
                    <label class="label">Лид (необязательно)</label>
                    <select v-model="sendForm.lead_id" class="field">
                        <option :value="null">Без привязки</option>
                        <option v-for="lead in leads" :key="lead.id" :value="lead.id">
                            {{ lead.number }} — {{ lead.title }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="label">Кому (через запятую)</label>
                    <input v-model="sendForm.to_raw" type="text" class="field" placeholder="client@example.com" />
                </div>
                <div>
                    <label class="label">Тема</label>
                    <input v-model="sendForm.subject" type="text" class="field" />
                </div>
                <div>
                    <label class="label">Текст</label>
                    <textarea v-model="sendForm.body" rows="6" class="field" />
                </div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">От: {{ fromEmail }}</p>
                <button type="submit" class="secondary-button" :disabled="sendForm.processing">Отправить</button>
            </form>

            <div class="space-y-3 border border-zinc-200 p-4 dark:border-zinc-800">
                <h2 class="text-base font-semibold">Недавние цепочки</h2>
                <div v-if="threads.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">Писем пока нет.</div>
                <div
                    v-for="thread in threads"
                    :key="thread.id"
                    class="border border-zinc-200 p-3 text-sm dark:border-zinc-800"
                >
                    <div class="font-medium">{{ thread.subject }}</div>
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
import CrmLayout from '@/Layouts/CrmLayout.vue';

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
