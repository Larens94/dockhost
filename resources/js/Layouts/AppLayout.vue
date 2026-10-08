<!-- AppLayout.vue — AppLayout component.

  exports: defineProps
  used_by: none
  rules:   SMTP nav link visible only when is_admin (admin navItems block). Nav labels come from shared panel translations.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_smtp | Nav link SMTP for admins.
  agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Locale-aware nav and account language link.
-->

<script setup>
import { usePanelTranslations } from '../composables/usePanelTranslations';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
});

const STORAGE_KEY = 'dokhosts.sidebar.collapsed';

const page = usePage();
const { t } = usePanelTranslations();
const appName = computed(() => page.props.appName || 'DokHosts');
const appVersion = computed(() => page.props.appVersion || 'DokHosts');
const user = computed(() => page.props.auth?.user);
const path = computed(() => page.url.split('?')[0]);

const collapsed = ref(
    typeof window !== 'undefined' && window.localStorage.getItem(STORAGE_KEY) === '1',
);
const accountOpen = ref(false);
const accountMenu = ref(null);

const toggleCollapsed = () => {
    collapsed.value = !collapsed.value;
    window.localStorage.setItem(STORAGE_KEY, collapsed.value ? '1' : '0');
};

const closeAccountMenu = (event) => {
    if (accountMenu.value && !accountMenu.value.contains(event.target)) {
        accountOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeAccountMenu);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeAccountMenu);
});

const initials = computed(() => {
    const name = user.value?.name || user.value?.email || 'A';

    return name
        .split(/[\s@._-]+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');
});

const isActive = (href) => path.value === href || path.value.startsWith(`${href}/`);

const isAdmin = computed(() => Boolean(user.value?.is_admin));

const navItems = computed(() => {
    if (!isAdmin.value) {
        return [{ href: '/domains', label: t('layout.nav.my_hosting'), icon: 'globe' }];
    }

    return [
        { href: '/customers', label: t('layout.nav.customers'), icon: 'users' },
        { href: '/subscriptions', label: t('layout.nav.spaces'), icon: 'squares' },
        { href: '/domains', label: t('layout.nav.domains'), icon: 'globe' },
        { href: '/service-plans', label: t('layout.nav.plans'), icon: 'stack' },
        { href: '/infrastructures', label: t('layout.nav.infrastructures'), icon: 'server' },
        { href: '/laravel-toolkit', label: t('layout.nav.toolkit'), icon: 'code' },
        { href: '/panel/smtp', label: t('layout.nav.smtp'), icon: 'mail' },
        { href: '/panel/audit', label: t('layout.nav.audit'), icon: 'code' },
    ];
});
</script>

<template>
    <div class="min-h-screen bg-neutral-50 text-neutral-900">
        <Head :title="title" />

        <aside
            class="fixed inset-y-0 left-0 z-20 hidden flex-col bg-white transition-[width] duration-200 lg:flex"
            :class="collapsed ? 'w-[4.5rem]' : 'w-[17rem]'"
        >
            <button
                type="button"
                class="absolute top-3.5 -right-3 z-30 inline-flex h-8 w-8 items-center justify-center rounded-xl border border-neutral-200 bg-white text-neutral-800 shadow-sm hover:bg-neutral-50"
                :title="collapsed ? t('layout.open_menu') : t('layout.close_menu')"
                :aria-label="collapsed ? t('layout.open_menu') : t('layout.close_menu')"
                @click="toggleCollapsed"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                    <rect x="4" y="5" width="16" height="14" rx="2.5" />
                    <path stroke-linecap="round" d="M9 5v14" />
                </svg>
            </button>

            <div class="flex items-center px-4 pt-5 pb-6" :class="collapsed ? 'justify-center px-2' : 'pr-8'">
                <Link href="/customers" class="flex min-w-0 items-center gap-2.5" :title="appName">
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-neutral-900 text-[9px] font-semibold tracking-tight text-white"
                    >
                        DH
                    </span>
                    <span v-if="!collapsed" class="truncate text-sm font-medium text-neutral-900">{{ appName }}</span>
                    <svg
                        v-if="!collapsed"
                        class="h-4 w-4 shrink-0 text-neutral-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.75"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4M16 15l-4 4-4-4" />
                    </svg>
                </Link>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3" :class="collapsed ? 'px-2' : ''">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    :title="item.label"
                    class="flex min-h-11 items-center rounded-lg text-sm"
                    :class="[
                        collapsed ? 'justify-center px-0' : 'gap-3 px-3',
                        isActive(item.href)
                            ? 'bg-neutral-100 text-neutral-900'
                            : 'text-neutral-800 hover:bg-neutral-50',
                    ]"
                >
                    <svg
                        v-if="item.icon === 'users'"
                        class="h-5 w-5 shrink-0 text-neutral-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                        />
                    </svg>
                    <svg
                        v-else-if="item.icon === 'squares'"
                        class="h-5 w-5 shrink-0 text-neutral-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"
                        />
                    </svg>
                    <svg
                        v-else-if="item.icon === 'stack'"
                        class="h-5 w-5 shrink-0 text-neutral-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m0 0a2.246 2.246 0 0 0-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0 1 21 12v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6c0-.98.626-1.813 1.5-2.122"
                        />
                    </svg>
                    <svg
                        v-else-if="item.icon === 'mail'"
                        class="h-5 w-5 shrink-0 text-neutral-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"
                        />
                    </svg>
                    <svg
                        v-else-if="item.icon === 'server'"
                        class="h-5 w-5 shrink-0 text-neutral-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z"
                        />
                    </svg>
                    <svg
                        v-else
                        class="h-5 w-5 shrink-0 text-neutral-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5"
                        />
                    </svg>
                    <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
                </Link>
            </nav>

            <div class="mt-auto px-4 pb-4 pt-6" :class="collapsed ? 'px-2' : ''">
                <div ref="accountMenu" class="relative">
                    <button
                        type="button"
                        class="flex w-full items-center rounded-lg py-1.5 text-left"
                        :class="collapsed ? 'justify-center' : 'gap-3'"
                        :title="user?.email || t('layout.account')"
                        aria-haspopup="menu"
                        :aria-expanded="accountOpen"
                        @click.stop="accountOpen = !accountOpen"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-[11px] font-medium text-neutral-700"
                        >
                            {{ initials }}
                        </span>
                        <span v-if="!collapsed" class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-neutral-900">{{ t('layout.account') }}</span>
                            <span class="block truncate text-xs text-neutral-500">{{ user?.email }}</span>
                        </span>
                        <svg
                            v-if="!collapsed"
                            class="h-4 w-4 shrink-0 text-neutral-400"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.75"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4M16 15l-4 4-4-4" />
                        </svg>
                    </button>

                    <div
                        v-if="accountOpen"
                        class="absolute bottom-full left-0 z-30 mb-2 w-full min-w-[12rem] rounded-xl border border-neutral-200 bg-white p-1 shadow-lg"
                        :class="collapsed ? 'left-full ml-2' : ''"
                        role="menu"
                    >
                        <Link
                            href="/account/two-factor"
                            role="menuitem"
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-neutral-700 hover:bg-neutral-50"
                            @click="accountOpen = false"
                        >
                            <svg class="h-4 w-4 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 0 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                            {{ t('layout.security') }}
                        </Link>
                        <Link
                            href="/account/locale"
                            role="menuitem"
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-neutral-700 hover:bg-neutral-50"
                            @click="accountOpen = false"
                        >
                            <svg class="h-4 w-4 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5a17.9 17.9 0 0 1-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                            </svg>
                            {{ t('layout.language') }}
                        </Link>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            role="menuitem"
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-neutral-700 hover:bg-neutral-50"
                            @click="accountOpen = false"
                        >
                            <svg class="h-4 w-4 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25"
                                />
                            </svg>
                            {{ t('layout.logout') }}
                        </Link>
                    </div>
                </div>

                <p v-if="!collapsed" class="mt-3 text-center text-xs text-neutral-400">{{ t('layout.version') }} {{ appVersion }}</p>
            </div>
        </aside>

        <div class="transition-[padding] duration-200" :class="collapsed ? 'lg:pl-[4.5rem]' : 'lg:pl-[17rem]'">
            <header
                class="sticky top-0 z-10 flex items-center justify-between border-b border-neutral-200 bg-white/90 px-4 py-3 backdrop-blur lg:hidden"
            >
                <p class="text-sm font-semibold tracking-normal">{{ appName }}</p>
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <Link href="/customers">{{ t('layout.mobile.customers') }}</Link>
                    <Link href="/subscriptions">{{ t('layout.mobile.spaces') }}</Link>
                    <Link href="/service-plans">{{ t('layout.mobile.plans') }}</Link>
                    <Link href="/infrastructures">{{ t('layout.mobile.infra') }}</Link>
                    <Link href="/laravel-toolkit">{{ t('layout.mobile.toolkit') }}</Link>
                    <Link v-if="isAdmin" href="/panel/smtp">{{ t('layout.nav.smtp') }}</Link>
                    <Link href="/account/locale">{{ t('layout.language') }}</Link>
                    <Link href="/logout" method="post" as="button">{{ t('layout.logout') }}</Link>
                </div>
            </header>

            <header
                class="sticky top-0 z-10 hidden items-center justify-between border-b border-neutral-200 bg-white/90 px-6 py-3 backdrop-blur lg:flex"
            >
                <div class="min-w-0">
                    <p class="text-[11px] text-neutral-500">{{ t('layout.panel_eyebrow') }}</p>
                    <p class="truncate text-sm font-medium tracking-normal">{{ title || appName }}</p>
                </div>
                <span
                    class="rounded-lg border border-neutral-200 bg-white px-2.5 py-1 text-[11px] font-medium text-neutral-600"
                >
                    {{ t('layout.hosting_badge') }}
                </span>
            </header>

            <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
                <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <h1 class="text-2xl font-semibold tracking-normal">{{ title }}</h1>
                        <p v-if="description" class="mt-1 max-w-3xl text-sm break-words text-neutral-500 [overflow-wrap:anywhere]">
                            {{ description }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <slot name="actions" />
                    </div>
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
