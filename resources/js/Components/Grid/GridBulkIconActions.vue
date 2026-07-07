<template>
  <div
    v-if="visibleActions.length > 0"
    class="flex items-center gap-1 rounded-xl border border-zinc-200 bg-white px-1 py-1 dark:border-zinc-700 dark:bg-zinc-900"
  >
    <span
      v-if="selectedCount > 0"
      class="px-2 text-xs font-medium text-zinc-500 dark:text-zinc-400"
      :title="`Выбрано: ${selectedCount}`"
    >
      {{ selectedCount }}
    </span>

    <button
      v-for="action in visibleActions"
      :key="action.key"
      type="button"
      :class="buttonClass(action)"
      :disabled="disabled || action.disabled || selectedCount === 0"
      :title="action.title"
      @click="$emit('action', action.key)"
    >
      <component :is="action.icon" class="h-4 w-4" />
      <span v-if="action.label" class="sr-only">{{ action.label }}</span>
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  selectedCount: {
    type: Number,
    default: 0,
  },
  actions: {
    type: Array,
    default: () => [],
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['action']);

const visibleActions = computed(() => (props.actions ?? []).filter((action) => action?.key && action?.icon));

function buttonClass(action) {
  const base = 'inline-flex items-center justify-center rounded-lg p-2 transition disabled:cursor-not-allowed disabled:opacity-40';

  if (action.danger) {
    return `${base} text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40`;
  }

  return `${base} text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800`;
}
</script>
