import axios from 'axios';
import { ref, watch } from 'vue';

export function useMobileShell() {
    const tasks = ref([]);
    const orders = ref([]);
    const recentDocuments = ref([]);
    const attentionDocuments = ref([]);
    const overdueTaskCount = ref(0);
    const tasksLoading = ref(false);
    const ordersLoading = ref(false);
    const documentsLoading = ref(false);
    const shellError = ref('');

    async function loadTasks(search = '') {
        tasksLoading.value = true;
        shellError.value = '';

        try {
            const { data } = await axios.get(route('mobile.shell.tasks'), {
                headers: { Accept: 'application/json' },
                params: search.trim() !== '' ? { q: search.trim() } : {},
            });
            tasks.value = data.tasks ?? [];
            overdueTaskCount.value = data.overdue_count ?? 0;
        } catch (exception) {
            shellError.value = exception.response?.data?.message ?? 'Не удалось загрузить задачи.';
        } finally {
            tasksLoading.value = false;
        }
    }

    async function loadOrders(search = '') {
        ordersLoading.value = true;
        shellError.value = '';

        try {
            const { data } = await axios.get(route('mobile.shell.orders'), {
                headers: { Accept: 'application/json' },
                params: search.trim() !== '' ? { q: search.trim() } : {},
            });
            orders.value = data.orders ?? [];
        } catch (exception) {
            shellError.value = exception.response?.data?.message ?? 'Не удалось загрузить заказы.';
        } finally {
            ordersLoading.value = false;
        }
    }

    async function loadDocuments(search = '') {
        documentsLoading.value = true;
        shellError.value = '';

        try {
            const { data } = await axios.get(route('mobile.shell.documents'), {
                headers: { Accept: 'application/json' },
                params: search.trim() !== '' ? { q: search.trim() } : {},
            });
            recentDocuments.value = data.recent ?? [];
            attentionDocuments.value = data.attention ?? [];
        } catch (exception) {
            shellError.value = exception.response?.data?.message ?? 'Не удалось загрузить документы.';
        } finally {
            documentsLoading.value = false;
        }
    }

    function loadTab(tab, search = '') {
        if (tab === 'tasks') {
            return loadTasks(search);
        }

        if (tab === 'orders') {
            return loadOrders(search);
        }

        if (tab === 'documents') {
            return loadDocuments(search);
        }

        return Promise.resolve();
    }

    return {
        tasks,
        orders,
        recentDocuments,
        attentionDocuments,
        overdueTaskCount,
        tasksLoading,
        ordersLoading,
        documentsLoading,
        shellError,
        loadTasks,
        loadOrders,
        loadDocuments,
        loadTab,
    };
}

export function useMobileShellTabLoader(activeTab, search, loadTab) {
    watch([activeTab, search], ([tab, needle]) => {
        if (tab === 'chats' || tab === 'thread') {
            return;
        }

        loadTab(tab, needle);
    });
}
