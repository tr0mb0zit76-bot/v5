<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Module Manager
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <!-- Header with install button -->
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-medium text-gray-900">
                                Installed Modules
                            </h3>
                            <button 
                                @click="showInstallModal = true"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Install Module
                            </button>
                        </div>

                        <!-- Loading state -->
                        <div v-if="loading" class="text-center py-8">
                            <svg class="animate-spin h-8 w-8 text-gray-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="mt-2 text-gray-500">Loading modules...</p>
                        </div>

                        <!-- Empty state -->
                        <div v-else-if="modules.length === 0" class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No modules installed</h3>
                            <p class="mt-1 text-sm text-gray-500">Get started by installing a new module.</p>
                            <div class="mt-6">
                                <button
                                    @click="showInstallModal = true"
                                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                >
                                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Install Module
                                </button>
                            </div>
                        </div>

                        <!-- Modules table -->
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Module
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Version
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Order
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="module in modules" :key="module.id" :class="{'bg-gray-50': !module.enabled}">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                        <svg class="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                                        </svg>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ module.name }}
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        {{ module.slug }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">{{ module.version }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="{
                                                'px-2 inline-flex text-xs leading-5 font-semibold rounded-full': true,
                                                'bg-green-100 text-green-800': module.enabled,
                                                'bg-red-100 text-red-800': !module.enabled
                                            }">
                                                {{ module.enabled ? 'Enabled' : 'Disabled' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div class="flex items-center space-x-2">
                                                <button 
                                                    v-if="module.order > 1"
                                                    @click="moveModule(module, 'up')"
                                                    class="text-gray-400 hover:text-gray-600"
                                                >
                                                    ↑
                                                </button>
                                                <span>{{ module.order }}</span>
                                                <button 
                                                    v-if="module.order < modules.length"
                                                    @click="moveModule(module, 'down')"
                                                    class="text-gray-400 hover:text-gray-600"
                                                >
                                                    ↓
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button 
                                                v-if="!module.enabled"
                                                @click="toggleModule(module)"
                                                class="text-green-600 hover:text-green-900 mr-3"
                                                :disabled="toggling === module.id"
                                            >
                                                <span v-if="toggling === module.id" class="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-green-600"></span>
                                                <span v-else>Enable</span>
                                            </button>
                                            <button 
                                                v-else
                                                @click="toggleModule(module)"
                                                class="text-yellow-600 hover:text-yellow-900 mr-3"
                                                :disabled="toggling === module.id"
                                            >
                                                <span v-if="toggling === module.id" class="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-yellow-600"></span>
                                                <span v-else>Disable</span>
                                            </button>
                                            <button 
                                                v-if="module.name !== 'Core' && module.name !== 'ModuleManager'"
                                                @click="confirmUninstall(module)"
                                                class="text-red-600 hover:text-red-900"
                                                :disabled="deleting === module.id"
                                            >
                                                <span v-if="deleting === module.id" class="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-red-600"></span>
                                                <span v-else>Uninstall</span>
                                            </button>
                                            <span v-else class="text-gray-400 text-xs">Protected</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Install Module Modal -->
        <div v-if="showInstallModal" class="fixed inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">
                                    Install New Module
                                </h3>
                                <div class="mt-4">
                                    <label for="module-name" class="block text-sm font-medium text-gray-700">
                                        Module Name
                                    </label>
                                    <input 
                                        id="module-name"
                                        v-model="newModuleName"
                                        type="text"
                                        placeholder="e.g., Order, Contractor, Finance"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                        @keyup.enter="installModule"
                                    />
                                    <p class="mt-2 text-sm text-gray-500">
                                        Enter the name of the module you want to install. This will create a new module structure.
                                    </p>
                                </div>
                                <div v-if="installError" class="mt-2 text-sm text-red-600">
                                    {{ installError }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button 
                            @click="installModule"
                            :disabled="!newModuleName || installing"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="installing" class="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>
                            {{ installing ? 'Installing...' : 'Install' }}
                        </button>
                        <button 
                            @click="showInstallModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Uninstall Confirmation Modal -->
        <div v-if="showUninstallModal" class="fixed inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">
                                    Uninstall Module
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Are you sure you want to uninstall <strong class="text-gray-900">{{ moduleToUninstall?.name }}</strong>?
                                        This action cannot be undone and will delete all module files and data.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button 
                            @click="uninstallModule"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Uninstall
                        </button>
                        <button 
                            @click="showUninstallModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Toast -->
        <div v-if="toast.show" class="fixed bottom-4 right-4 z-50 animate-fade-in-up">
            <div :class="{
                'bg-green-50 border-green-400 text-green-800': toast.type === 'success',
                'bg-red-50 border-red-400 text-red-800': toast.type === 'error'
            }" class="border rounded-lg p-4 shadow-lg max-w-sm">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg v-if="toast.type === 'success'" class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <svg v-else class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium">{{ toast.message }}</p>
                    </div>
                    <div class="ml-auto pl-3">
                        <button @click="toast.show = false" class="inline-flex text-gray-400 hover:text-gray-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

// State
const modules = ref([])
const loading = ref(true)
const toggling = ref(null)
const deleting = ref(null)
const installing = ref(false)
const showInstallModal = ref(false)
const showUninstallModal = ref(false)
const newModuleName = ref('')
const moduleToUninstall = ref(null)
const installError = ref('')
const toast = ref({
    show: false,
    message: '',
    type: 'success'
})

// Methods
const fetchModules = async () => {
    loading.value = true
    try {
        const response = await axios.get('/api/modules')
        modules.value = response.data
    } catch (error) {
        console.error('Error fetching modules:', error)
        showToast('Failed to load modules', 'error')
    } finally {
        loading.value = false
    }
}

const toggleModule = async (module) => {
    toggling.value = module.id
    try {
        const response = await axios.post(`/api/modules/${module.id}/toggle`)
        await fetchModules()
        showToast(response.data.message, 'success')
    } catch (error) {
        console.error('Error toggling module:', error)
        showToast('Failed to toggle module', 'error')
    } finally {
        toggling.value = null
    }
}

const installModule = async () => {
    if (!newModuleName.value.trim()) return
    
    installing.value = true
    installError.value = ''
    
    try {
        const response = await axios.post('/api/modules/install', {
            name: newModuleName.value.trim()
        })
        
        showInstallModal.value = false
        newModuleName.value = ''
        await fetchModules()
        showToast(response.data.message, 'success')
    } catch (error) {
        console.error('Error installing module:', error)
        installError.value = error.response?.data?.error || 'Failed to install module'
    } finally {
        installing.value = false
    }
}

const confirmUninstall = (module) => {
    moduleToUninstall.value = module
    showUninstallModal.value = true
}

const uninstallModule = async () => {
    if (!moduleToUninstall.value) return
    
    deleting.value = moduleToUninstall.value.id
    try {
        await axios.delete(`/api/modules/${moduleToUninstall.value.id}`)
        showUninstallModal.value = false
        await fetchModules()
        showToast(`Module ${moduleToUninstall.value.name} uninstalled successfully`, 'success')
        moduleToUninstall.value = null
    } catch (error) {
        console.error('Error uninstalling module:', error)
        showToast('Failed to uninstall module', 'error')
    } finally {
        deleting.value = null
    }
}

const moveModule = async (module, direction) => {
    const currentIndex = modules.value.findIndex(m => m.id === module.id)
    const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1
    
    if (targetIndex < 0 || targetIndex >= modules.value.length) return
    
    // Swap orders
    const targetModule = modules.value[targetIndex]
    const currentOrder = module.order
    const targetOrder = targetModule.order
    
    try {
        // Update both modules' orders
        await axios.put(`/api/modules/${module.id}`, { order: targetOrder })
        await axios.put(`/api/modules/${targetModule.id}`, { order: currentOrder })
        await fetchModules()
        showToast('Module order updated', 'success')
    } catch (error) {
        console.error('Error moving module:', error)
        showToast('Failed to update module order', 'error')
    }
}

const showToast = (message, type = 'success') => {
    toast.value = {
        show: true,
        message,
        type
    }
    
    setTimeout(() => {
        toast.value.show = false
    }, 3000)
}

// Lifecycle
onMounted(() => {
    fetchModules()
})
</script>

<style scoped>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-up {
    animation: fadeInUp 0.3s ease-out;
}
</style>