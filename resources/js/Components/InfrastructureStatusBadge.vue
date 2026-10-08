<!-- InfrastructureStatusBadge.vue — InfrastructureStatusBadge component.

  exports: defineProps
  used_by: none
  rules:   none
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
-->

<script setup>
import { computed } from 'vue';
import { usePanelTranslations } from '../composables/usePanelTranslations';

const { t } = usePanelTranslations();

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
});

const label = computed(() => {
    const key = `status.${props.status}`;
    const translated = t(key);

    return translated === key ? props.status : translated;
});

const badgeClass = computed(() => {
    if (props.status === 'ready' || props.status === 'deployed') {
        return 'bg-emerald-50 text-emerald-800';
    }

    if (props.status === 'failed') {
        return 'bg-red-50 text-red-800';
    }

    if (props.status === 'degraded') {
        return 'bg-amber-50 text-amber-800';
    }

    if (props.status === 'deploying') {
        return 'bg-sky-50 text-sky-800';
    }

    return 'bg-zinc-100 text-zinc-700';
});
</script>

<template>
    <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-medium" :class="badgeClass">
        {{ label }}
    </span>
</template>
