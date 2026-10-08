<!-- ServicePicker.vue — ServicePicker component.

  exports: defineProps | emit:toggle
  used_by: none
  rules:   none
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
-->

<script setup>
import { usePanelTranslations } from '../composables/usePanelTranslations';

const { t } = usePanelTranslations();

const props = defineProps({
    services: {
        type: Array,
        required: true,
    },
    selected: {
        type: Array,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['toggle']);

const isSelected = (service) => props.selected.includes(service.key);

const onSelect = (service) => {
    if (props.disabled || service.required) {
        return;
    }

    emit('toggle', service.key);
};
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2">
        <button
            v-for="service in services"
            :key="service.key"
            type="button"
            class="flex items-start gap-3 rounded-xl border bg-white p-3.5 text-left transition"
            :class="
                isSelected(service)
                    ? 'border-zinc-900 ring-2 ring-zinc-900/15'
                    : 'border-neutral-200 hover:border-zinc-300'
            "
            :disabled="disabled || service.required"
            :aria-pressed="isSelected(service)"
            @click="onSelect(service)"
        >
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-white"
                :class="{
                    'bg-[#c3362d]': service.key === 'mariadb',
                    'bg-[#336791]': service.key === 'postgres',
                    'bg-zinc-800': service.key === 'sftp',
                    'bg-[#6c78af]': service.key === 'phpmyadmin',
                    'bg-[#325d9a]': service.key === 'pgadmin',
                    'bg-[#dc382d]': service.key === 'redis',
                    'bg-[#c72c48]': service.key === 'minio',
                }"
            >
                <svg v-if="service.key === 'mariadb'" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                    <path d="M4 7c0-2 3.6-4 8-4s8 2 8 4v10c0 2-3.6 4-8 4s-8-2-8-4zm8-2.2C8.5 4.8 6 6 6 7s2.5 2.2 6 2.2S18 8 18 7s-2.5-2.2-6-2.2z" />
                </svg>
                <svg v-else-if="service.key === 'postgres'" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                    <path d="M12 3c-2.4 0-4.4 1.7-4.9 4.1C4.8 8 4 9.7 4 11.4c0 2.4 1.5 4.4 3.6 5.2.4 2.2 2.4 3.9 4.7 3.9s4.3-1.7 4.7-3.9c2.1-.8 3.6-2.8 3.6-5.2 0-1.7-.8-3.4-2.1-4.3C16.4 4.7 14.4 3 12 3zm0 2c1.6 0 3 1 3.5 2.5L12 9.2 8.5 7.5C9 6 10.4 5 12 5z" />
                </svg>
                <svg v-else-if="service.key === 'sftp'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M8 10V7a4 4 0 1 1 8 0v3M6 10h12v10H6z" />
                </svg>
                <svg v-else-if="service.key === 'phpmyadmin'" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                    <path d="M5 6h14v3H5zm0 5h6v7H5zm8 0h6v7h-6z" />
                </svg>
                <svg v-else-if="service.key === 'pgadmin'" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                    <path d="M7 4h7a5 5 0 0 1 0 10h-4v6H7zm3 3v4h4a2 2 0 0 0 0-4z" />
                </svg>
                <svg v-else-if="service.key === 'redis'" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                    <path d="M4 8.2 12 5l8 3.2-8 3.3zm0 4 8 3.3 8-3.3v3.3L12 19l-8-3.5z" />
                </svg>
                <svg v-else-if="service.key === 'minio'" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                    <path d="M6 7h12l2 5-8 8-8-8zm2.2 2 1.3 3h5l1.3-3z" />
                </svg>
                <span v-else class="text-xs font-bold">{{ service.label.slice(0, 2) }}</span>
            </span>
            <span class="min-w-0 flex-1">
                <span class="flex items-center justify-between gap-2">
                    <span class="text-sm font-semibold">{{ service.label }}</span>
                    <span
                        class="rounded-full px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide"
                        :class="service.required ? 'bg-zinc-100 text-zinc-500' : isSelected(service) ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-500'"
                    >
                        {{ service.required ? t('picker.base') : isSelected(service) ? t('picker.on') : t('picker.off') }}
                    </span>
                </span>
                <span class="mt-0.5 block text-xs break-words text-zinc-500 [overflow-wrap:anywhere]">{{
                    service.summary
                }}</span>
                <span
                    v-if="service.hostname"
                    class="mt-1 block font-mono text-[11px] break-all text-zinc-400"
                >{{ service.hostname }}</span>
            </span>
        </button>
    </div>
</template>
