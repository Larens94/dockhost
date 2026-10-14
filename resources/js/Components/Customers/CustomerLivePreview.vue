<!-- CustomerLivePreview.vue — CustomerLivePreview component.

  exports: defineProps
  used_by: resources/js/Pages/Customers/Create.vue
  rules:   Live interactive preview reflecting customer model state & storage slug.
  agent:   antigravity | 2026-10-14 | modern UI/UX redesign
-->

<script setup>
import { computed, ref } from 'vue';
import { usePanelTranslations } from '../../composables/usePanelTranslations';

const { t } = usePanelTranslations();

const props = defineProps({
    name: {
        type: String,
        default: '',
    },
    email: {
        type: String,
        default: '',
    },
    notes: {
        type: String,
        default: '',
    },
});

const copied = ref(false);

// Generate storage slug identical to Customer::storageSlug() in PHP:
// Str::slug($this->name) !== '' ? $slug : 'customer-new'
const computedSlug = computed(() => {
    const raw = props.name.trim();
    if (!raw) {
        return 'customer-new';
    }

    return raw
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '') || 'customer-new';
});

// Dynamic initials based on typed name
const initials = computed(() => {
    const raw = props.name.trim();
    if (!raw) {
        return 'DH';
    }

    const parts = raw.split(/[\s@._-]+/).filter(Boolean);
    if (parts.length === 1) {
        return parts[0].substring(0, 2).toUpperCase();
    }

    return (parts[0][0] + parts[1][0]).toUpperCase();
});

// Vibrant gradient selection based on initials
const avatarGradient = computed(() => {
    const gradients = [
        'from-sky-500 via-indigo-500 to-purple-600',
        'from-emerald-500 via-teal-500 to-cyan-600',
        'from-amber-500 via-orange-500 to-rose-500',
        'from-violet-600 via-fuchsia-600 to-pink-500',
        'from-blue-600 via-cyan-500 to-emerald-500',
    ];

    let hash = 0;
    const str = props.name || 'DH';
    for (let i = 0; i < str.length; i++) {
        hash = (hash + str.charCodeAt(i)) % gradients.length;
    }

    return gradients[hash];
});

const copySlug = async () => {
    try {
        await navigator.clipboard.writeText(computedSlug.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // Fallback or ignore
    }
};
</script>

<template>
    <div class="space-y-6">
        <!-- Live Card Container -->
        <div class="relative overflow-hidden rounded-2xl border border-neutral-200/80 bg-white p-6 shadow-sm transition-all duration-300 hover:shadow-md">
            <!-- Subtle accent top strip -->
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r" :class="avatarGradient" />

            <!-- Card Header with Live Indicator -->
            <div class="flex items-center justify-between pb-4 border-b border-neutral-100">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                    </span>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">
                        {{ t('customers_form.live_preview') }}
                    </span>
                </div>

                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-medium text-emerald-700 border border-emerald-200/60">
                    {{ t('customers_form.status_ready') }}
                </span>
            </div>

            <!-- Profile Overview Section -->
            <div class="mt-5 flex items-start gap-4">
                <!-- Avatar with dynamic gradient & initials -->
                <div
                    class="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-md font-semibold text-lg tracking-tight select-none transition-transform duration-300 hover:scale-105"
                    :class="avatarGradient"
                >
                    {{ initials }}
                    <div class="absolute -bottom-1 -right-1 h-4 w-4 rounded-full border-2 border-white bg-emerald-500" title="Active" />
                </div>

                <!-- Customer Details -->
                <div class="min-w-0 flex-1">
                    <h3 class="truncate text-lg font-bold text-neutral-900 tracking-tight">
                        {{ name.trim() || t('customers_form.name_placeholder') }}
                    </h3>

                    <p class="mt-0.5 flex items-center gap-1.5 truncate text-xs text-neutral-500">
                        <svg class="h-3.5 w-3.5 shrink-0 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                        <span>{{ email.trim() || 'contact@example.com' }}</span>
                    </p>
                </div>
            </div>

            <!-- Slug & Storage Path Preview Box -->
            <div class="mt-5 rounded-xl border border-neutral-100 bg-neutral-50/70 p-3.5">
                <div class="flex items-center justify-between text-xs text-neutral-500 mb-1.5">
                    <span class="font-medium flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                        </svg>
                        {{ t('customers_form.slug_preview') }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 text-[11px] font-medium text-neutral-600 hover:text-neutral-900 transition"
                        @click="copySlug"
                    >
                        <svg v-if="copied" class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                        </svg>
                        <span>{{ copied ? 'Copied' : 'Copy' }}</span>
                    </button>
                </div>

                <div class="flex items-center gap-1 font-mono text-xs text-neutral-800 break-all select-all bg-white px-2.5 py-1.5 rounded-lg border border-neutral-200/80">
                    <span class="text-neutral-400">/storage/customers/</span>
                    <span class="font-semibold text-neutral-900">{{ computedSlug }}</span>
                    <span class="text-neutral-400">/</span>
                </div>
            </div>

            <!-- Notes preview snippet if present -->
            <div v-if="notes.trim()" class="mt-4 pt-3.5 border-t border-neutral-100 text-xs">
                <span class="font-medium text-neutral-500 uppercase tracking-wider text-[10px] block mb-1">
                    {{ t('common.notes') }}
                </span>
                <p class="text-neutral-700 italic line-clamp-2 bg-neutral-50/50 p-2 rounded-lg border border-neutral-100">
                    "{{ notes.trim() }}"
                </p>
            </div>
        </div>

        <!-- DokHosts Multi-tenant Flow Guide -->
        <div class="rounded-2xl border border-neutral-200/80 bg-white p-6 shadow-sm">
            <h4 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-4">
                <svg class="h-4 w-4 text-neutral-500" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.143 2.143L15.75 6" />
                </svg>
                <span>{{ t('customers_form.architecture_title') }}</span>
            </h4>

            <div class="relative space-y-4 before:absolute before:left-3.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-neutral-100">
                <!-- Step 1 -->
                <div class="relative flex items-start gap-3">
                    <div class="relative z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-neutral-900 text-white font-semibold text-xs shadow-xs ring-4 ring-white">
                        1
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-neutral-900">{{ t('customers_form.step_1_title') }}</p>
                        <p class="mt-0.5 text-xs text-neutral-500 leading-relaxed">{{ t('customers_form.step_1_desc') }}</p>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="relative flex items-start gap-3">
                    <div class="relative z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-neutral-500 font-semibold text-xs ring-4 ring-white">
                        2
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-neutral-700">{{ t('customers_form.step_2_title') }}</p>
                        <p class="mt-0.5 text-xs text-neutral-500 leading-relaxed">{{ t('customers_form.step_2_desc') }}</p>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="relative flex items-start gap-3">
                    <div class="relative z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-neutral-500 font-semibold text-xs ring-4 ring-white">
                        3
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-neutral-700">{{ t('customers_form.step_3_title') }}</p>
                        <p class="mt-0.5 text-xs text-neutral-500 leading-relaxed">{{ t('customers_form.step_3_desc') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
