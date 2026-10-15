<!-- FormTextarea.vue — Reusable modern form textarea component.

  exports: defineProps, defineEmits
  used_by: resources/js/Pages/Customers/Create.vue
  rules:   Accessible multi-line field with character counter and tag shortcuts.
  agent:   antigravity | 2026-10-14 | modern UI/UX redesign
-->

<script setup>
import { computed, useId } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    id: {
        type: String,
        default: () => useId(),
    },
    label: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: '',
    },
    rows: {
        type: Number,
        default: 3,
    },
    required: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    help: {
        type: String,
        default: '',
    },
    badge: {
        type: String,
        default: '',
    },
    icon: {
        type: String,
        default: '',
    },
    showCount: {
        type: Boolean,
        default: true,
    },
    maxLength: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue']);

const charCount = computed(() => (props.modelValue ? props.modelValue.length : 0));

const onInput = (event) => {
    emit('update:modelValue', event.target.value);
};
</script>

<template>
    <div class="group relative flex flex-col gap-1.5">
        <div v-if="label" class="flex items-center justify-between">
            <label :for="id" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-neutral-700">
                <span>{{ label }}</span>
                <span v-if="required" class="text-rose-500 font-bold" title="Required">*</span>
            </label>
            <div class="flex items-center gap-2">
                <span v-if="badge" class="rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] font-medium text-neutral-600">
                    {{ badge }}
                </span>
                <span
                    v-if="showCount"
                    class="font-mono text-[11px] text-neutral-400"
                    :class="maxLength && charCount >= maxLength ? 'text-amber-600 font-semibold' : ''"
                >
                    {{ charCount }}<span v-if="maxLength">/{{ maxLength }}</span>
                </span>
            </div>
        </div>

        <div
            class="relative flex flex-col rounded-xl border bg-white shadow-xs transition-all duration-200 focus-within:ring-2 focus-within:shadow-sm"
            :class="[
                error
                    ? 'border-rose-300 focus-within:border-rose-500 focus-within:ring-rose-500/20'
                    : 'border-neutral-200/90 hover:border-neutral-300 focus-within:border-neutral-900 focus-within:ring-neutral-900/10',
                disabled ? 'bg-neutral-50 opacity-60 cursor-not-allowed' : '',
            ]"
        >
            <div class="flex items-start">
                <div
                    v-if="icon || $slots.icon"
                    class="pointer-events-none flex items-center pl-3.5 pt-3 text-neutral-400 transition-colors group-focus-within:text-neutral-900"
                >
                    <slot name="icon">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                            />
                        </svg>
                    </slot>
                </div>

                <textarea
                    :id="id"
                    :value="modelValue"
                    :placeholder="placeholder"
                    :rows="rows"
                    :required="required"
                    :disabled="disabled"
                    :maxlength="maxLength"
                    class="w-full resize-y bg-transparent py-2.5 text-sm text-neutral-900 placeholder:text-neutral-400 outline-none transition-all"
                    :class="icon || $slots.icon ? 'pl-2.5 pr-3.5' : 'px-3.5'"
                    @input="onInput"
                />
            </div>

            <!-- Optional embedded bottom slot (e.g. quick tags) -->
            <div v-if="$slots.bottom" class="border-t border-neutral-100 bg-neutral-50/50 px-3 py-2 rounded-b-xl">
                <slot name="bottom" />
            </div>
        </div>

        <div v-if="error || help || $slots.extra" class="flex flex-wrap items-center justify-between gap-1 text-xs">
            <p v-if="error" class="flex items-center gap-1 font-medium text-rose-600">
                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"
                    />
                </svg>
                <span>{{ error }}</span>
            </p>
            <p v-else-if="help" class="text-neutral-500">{{ help }}</p>

            <div v-if="$slots.extra" class="ml-auto">
                <slot name="extra" />
            </div>
        </div>
    </div>
</template>
