<!-- FormInput.vue — Reusable modern form input component.

  exports: defineProps, defineEmits
  used_by: resources/js/Pages/Customers/Create.vue
  rules:   Accessible form field with leading icon, clear button, and validation display.
  agent:   antigravity | 2026-10-14 | modern UI/UX redesign
-->

<script setup>
import { computed, useId } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number],
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
    type: {
        type: String,
        default: 'text',
    },
    placeholder: {
        type: String,
        default: '',
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
    clearable: {
        type: Boolean,
        default: false,
    },
    autocomplete: {
        type: String,
        default: 'off',
    },
});

const emit = defineEmits(['update:modelValue', 'clear']);

const hasValue = computed(() => props.modelValue !== null && props.modelValue !== undefined && String(props.modelValue).length > 0);

const onInput = (event) => {
    emit('update:modelValue', event.target.value);
};

const clearValue = () => {
    emit('update:modelValue', '');
    emit('clear');
};
</script>

<template>
    <div class="group relative flex flex-col gap-1.5">
        <div v-if="label" class="flex items-center justify-between">
            <label :for="id" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-neutral-700">
                <span>{{ label }}</span>
                <span v-if="required" class="text-rose-500 font-bold" title="Required">*</span>
            </label>
            <span v-if="badge" class="rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] font-medium text-neutral-600">
                {{ badge }}
            </span>
        </div>

        <div
            class="relative flex items-center rounded-xl border bg-white shadow-xs transition-all duration-200 focus-within:ring-2 focus-within:shadow-sm"
            :class="[
                error
                    ? 'border-rose-300 focus-within:border-rose-500 focus-within:ring-rose-500/20'
                    : 'border-neutral-200/90 hover:border-neutral-300 focus-within:border-neutral-900 focus-within:ring-neutral-900/10',
                disabled ? 'bg-neutral-50 opacity-60 cursor-not-allowed' : '',
            ]"
        >
            <!-- Leading Icon Slot or Built-in Icon -->
            <div
                v-if="icon || $slots.icon"
                class="pointer-events-none flex items-center pl-3.5 text-neutral-400 transition-colors group-focus-within:text-neutral-900"
            >
                <slot name="icon">
                    <!-- User / Person Icon -->
                    <svg
                        v-if="icon === 'user'"
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.75"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
                        />
                    </svg>

                    <!-- Mail Icon -->
                    <svg
                        v-else-if="icon === 'mail'"
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.75"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"
                        />
                    </svg>

                    <!-- Building / Organization Icon -->
                    <svg
                        v-else-if="icon === 'building'"
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.75"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.75c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12l3 3v15"
                        />
                    </svg>

                    <!-- Hash / Tag Icon -->
                    <svg
                        v-else-if="icon === 'tag'"
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.75"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.386a11.96 11.96 0 0 0 3.585-3.585c.486-.827.313-1.908-.386-2.607L9.568 3Z"
                        />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                    </svg>
                </slot>
            </div>

            <!-- Input Element -->
            <input
                :id="id"
                :type="type"
                :value="modelValue"
                :placeholder="placeholder"
                :required="required"
                :disabled="disabled"
                :autocomplete="autocomplete"
                class="w-full bg-transparent py-2.5 text-sm text-neutral-900 placeholder:text-neutral-400 outline-none transition-all"
                :class="[
                    icon || $slots.icon ? 'pl-2.5' : 'pl-3.5',
                    clearable && hasValue ? 'pr-8' : 'pr-3.5',
                ]"
                @input="onInput"
            />

            <!-- Clear Action Button -->
            <button
                v-if="clearable && hasValue && !disabled"
                type="button"
                tabindex="-1"
                class="absolute right-3 inline-flex h-5 w-5 items-center justify-center rounded-full text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 transition"
                title="Clear input"
                @click="clearValue"
            >
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Feedback & Helper Row -->
        <div v-if="error || help || $slots.extra" class="flex flex-wrap items-center justify-between gap-1 text-xs">
            <p v-if="error" class="flex items-center gap-1 font-medium text-rose-600 animate-fadeIn">
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
