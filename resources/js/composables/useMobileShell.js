import axios from 'axios';
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useMobileShell() {
    const page = usePage();
    const isExternalUser = () => Boolean(page.props.auth?.user?.is_external);

    const ordersIndexRoute = () => (
        isExternalUser() ? route('mobile.shell.counterparty.orders') : route('mobile.shell.orders')
    );

    const orderSummaryRoute = (orderId) => (
        isExternalUser()
            ? route('mobile.shell.counterparty.orders.summary', orderId)
            : route('mobile.shell.orders.summary', orderId)
    );

    const orderDocumentSlotsRoute = (orderId) => (
        isExternalUser()
            ? route('mobile.shell.counterparty.orders.document-slots', orderId)
            : route('mobile.shell.orders.document-slots', orderId)
    );
    const tasks = ref([]);
    const orders = ref([]);
    const recentDocuments = ref([]);
    const attentionDocuments = ref([]);
    const documentContractors = ref([]);
    const documentContractorOrders = ref([]);
    const orderDocumentChecklist = ref(null);
    const trakloLeads = ref([]);
    const overdueTaskCount = ref(0);
    const tasksLoading = ref(false);
    const ordersLoading = ref(false);
    const documentsLoading = ref(false);
    const documentContractorsLoading = ref(false);
    const documentContractorOrdersLoading = ref(false);
    const orderDocumentChecklistLoading = ref(false);
    const trakloLeadsLoading = ref(false);
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
            const { data } = await axios.get(ordersIndexRoute(), {
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

    async function loadDocumentContractors(search = '') {
        documentContractorsLoading.value = true;
        shellError.value = '';

        try {
            const { data } = await axios.get(route('mobile.shell.documents.contractors'), {
                headers: { Accept: 'application/json' },
                params: search.trim() !== '' ? { q: search.trim() } : {},
            });
            documentContractors.value = data.contractors ?? [];
        } catch (exception) {
            shellError.value = exception.response?.data?.message ?? 'Не удалось загрузить контрагентов.';
        } finally {
            documentContractorsLoading.value = false;
        }
    }

    async function loadDocumentContractorOrders(contractorId, search = '') {
        documentContractorOrdersLoading.value = true;
        shellError.value = '';

        try {
            const { data } = await axios.get(route('mobile.shell.documents.contractor-orders', contractorId), {
                headers: { Accept: 'application/json' },
                params: search.trim() !== '' ? { q: search.trim() } : {},
            });
            documentContractorOrders.value = data.orders ?? [];

            return data;
        } catch (exception) {
            shellError.value = exception.response?.data?.message ?? 'Не удалось загрузить заказы контрагента.';
            documentContractorOrders.value = [];

            return null;
        } finally {
            documentContractorOrdersLoading.value = false;
        }
    }

    async function loadOrderDocumentChecklist(orderId) {
        orderDocumentChecklistLoading.value = true;
        shellError.value = '';

        try {
            const { data } = await axios.get(route('mobile.shell.documents.order-checklist', orderId), {
                headers: { Accept: 'application/json' },
            });
            orderDocumentChecklist.value = data;

            return data;
        } catch (exception) {
            shellError.value = exception.response?.data?.message ?? 'Не удалось загрузить документы заказа.';
            orderDocumentChecklist.value = null;

            return null;
        } finally {
            orderDocumentChecklistLoading.value = false;
        }
    }

    async function loadTrakloLeads(search = '') {
        trakloLeadsLoading.value = true;
        shellError.value = '';

        try {
            const { data } = await axios.get(route('mobile.shell.traklo-leads'), {
                headers: { Accept: 'application/json' },
                params: search.trim() !== '' ? { q: search.trim() } : {},
            });
            trakloLeads.value = data.leads ?? [];
        } catch (exception) {
            shellError.value = exception.response?.data?.message ?? 'Не удалось загрузить заявки Traklo.';
        } finally {
            trakloLeadsLoading.value = false;
        }
    }

    async function loadOrderSummary(orderId) {
        const { data } = await axios.get(orderSummaryRoute(orderId), {
            headers: { Accept: 'application/json' },
        });

        return data;
    }

    async function loadLeadSummary(leadId) {
        const { data } = await axios.get(route('mobile.shell.leads.summary', leadId), {
            headers: { Accept: 'application/json' },
        });

        return data;
    }

    async function loadContractorSummary(contractorId) {
        const { data } = await axios.get(route('mobile.shell.contractors.summary', contractorId), {
            headers: { Accept: 'application/json' },
        });

        return data;
    }

    async function searchEntities(search = '', kind = null) {
        const params = {};

        if (search.trim() !== '') {
            params.q = search.trim();
        }

        if (kind) {
            params.kind = kind;
        }

        const { data } = await axios.get(route('mobile.shell.entity-chips'), {
            headers: { Accept: 'application/json' },
            params,
        });

        return data.entities ?? [];
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

        if (tab === 'leads') {
            return loadTrakloLeads(search);
        }

        return Promise.resolve();
    }

    async function saveLeadDraft(leadId, payload) {
        const { data } = await axios.patch(route('mobile.shell.leads.update', leadId), payload, {
            headers: { Accept: 'application/json' },
        });

        return data;
    }

    return {
        tasks,
        orders,
        recentDocuments,
        attentionDocuments,
        documentContractors,
        documentContractorOrders,
        orderDocumentChecklist,
        trakloLeads,
        overdueTaskCount,
        tasksLoading,
        ordersLoading,
        documentsLoading,
        documentContractorsLoading,
        documentContractorOrdersLoading,
        orderDocumentChecklistLoading,
        trakloLeadsLoading,
        shellError,
        loadTasks,
        loadOrders,
        loadDocuments,
        loadDocumentContractors,
        loadDocumentContractorOrders,
        loadOrderDocumentChecklist,
        loadTrakloLeads,
        loadTab,
        loadOrderSummary,
        loadLeadSummary,
        loadContractorSummary,
        saveLeadDraft,
        searchEntities,
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
