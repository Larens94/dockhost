<!-- Show.vue — Show component.

  exports: defineProps
  used_by: none
  rules:   Env di avvio copy says database session and a Secure cookie on this host. Do not claim file sessions.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
           grok-4.7 | cursor | 2026-09-22 | s_20260922_session_cookie | Describe database session and host cookie
           composer-2.5-fast | cursor | 2026-09-24 | s_domain_php | Card PHP on Generale tab
           composer-2.5-fast | cursor | 2026-09-25 | s_domain_site_env | Env sito, deploy, git read-only on Generale
           composer-2.5-fast | cursor | 2026-09-25 | s_access_mail | Invita button + copy for email invite flow
           composer-2.5-fast | cursor | 2026-10-11 | s_prod_foundations | Backup DB + align-from-prod on Database tab
           composer-2.5-fast | cursor | 2026-10-11 | s_runtime_env | APP_ENV / APP_DEBUG / LOG_LEVEL runtime block
           composer-2.5-fast | cursor | 2026-10-11 | s_domain_tabs_ia | Tabs: overview, app, database, sftp, access, danger
           composer-2.5-fast | cursor | 2026-10-11 | s_domain_ui_color | Semantic tab/card color accents for IA
           composer-2.5-fast | cursor | 2026-10-11 | s_db_import | SQL dump import on Database tab
           composer-2.5-fast | cursor | 2026-10-12 | s_domain_alias | Domains tab: aliases + subdomains
           composer-2.5-fast | cursor | 2026-10-12 | s_reveal_flash | Read flash.revealed_credential; show password on Database tab
           composer-2.5-fast | cursor | 2026-10-12 | s_reveal_alert | One credential alert modal; drop duplicate banners
-->

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import AppLayout from '../../Layouts/AppLayout.vue';

const { t } = usePanelTranslations();
import LaravelToolkitPanel from '../../Components/LaravelToolkitPanel.vue';
import StackToolkitPanel from '../../Components/StackToolkitPanel.vue';

const props = defineProps({
    domain: {
        type: Object,
        required: true,
    },
    infrastructures: {
        type: Array,
        default: () => [],
    },
    stackPresets: {
        type: Object,
        default: () => ({}),
    },
    laravelDeployPreset: {
        type: Object,
        default: () => ({}),
    },
    domainMembers: {
        type: Array,
        default: () => [],
    },
    canManageAccess: {
        type: Boolean,
        default: false,
    },
    canViewSubscription: {
        type: Boolean,
        default: false,
    },
    canMutateHosting: {
        type: Boolean,
        default: true,
    },
    canOpenDokploy: {
        type: Boolean,
        default: false,
    },
    canEditFqdn: {
        type: Boolean,
        default: false,
    },
    canDestroyDomain: {
        type: Boolean,
        default: false,
    },
    dokployAccessNote: {
        type: String,
        default: '',
    },
    phpSettings: {
        type: Object,
        default: () => ({
            memory_limit: '256M',
            upload_max_filesize: '64M',
            post_max_size: '64M',
            max_execution_time: 120,
            max_input_time: 120,
            artisan_memory_limit: '512M',
        }),
    },
    phpSettingsApplicable: {
        type: Boolean,
        default: false,
    },
    siteHosting: {
        type: Object,
        default: () => ({
            available: false,
            variables: [],
            git: null,
            error: null,
        }),
    },
    alignTargets: {
        type: Array,
        default: () => [],
    },
});

const isLaravelStack = computed(() => (props.domain.stack || 'none') === 'laravel');

const tabs = computed(() => {
    const base = [
        { id: 'generale', label: t('domains.tabs.general') },
        { id: 'hosts', label: t('domains.tabs.hosts') },
    ];

    if (isLaravelStack.value) {
        base.push({ id: 'laravel', label: t('domains.tabs.laravel') });
    } else {
        base.push({ id: 'sito', label: t('domains.tabs.site') });
    }

    base.push(
        { id: 'database', label: t('domains.tabs.database') },
        { id: 'sftp', label: t('domains.tabs.sftp') },
    );

    if (props.canManageAccess) {
        base.push({ id: 'accessi', label: t('domains.tabs.access') });
    }

    if (props.canDestroyDomain) {
        base.push({ id: 'pericolo', label: t('domains.tabs.danger') });
    }

    return base;
});

const accessForm = useForm({
    email: '',
    name: '',
    role: 'developer',
});

const roleOptions = computed(() => [
    { value: 'owner', label: t('roles.owner') },
    { value: 'developer', label: t('roles.developer') },
    { value: 'readonly', label: t('roles.readonly') },
]);

const page = usePage();
const allowedTabs = computed(() => tabs.value.map((tab) => tab.id));

const currentTab = computed(() => {
    const query = page.url.includes('?') ? page.url.slice(page.url.indexOf('?') + 1) : '';
    const tab = new URLSearchParams(query).get('tab');

    return allowedTabs.value.includes(tab) ? tab : 'generale';
});

const isAppTab = computed(() => currentTab.value === 'laravel' || currentTab.value === 'sito');

const tabHref = (tab) => {
    const base = `/domains/${props.domain.id}`;

    return tab === 'generale' ? base : `${base}?tab=${tab}`;
};

const tabTones = {
    generale: {
        active: 'border-zinc-800 text-zinc-900',
        idle: 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-800',
    },
    hosts: {
        active: 'border-blue-600 text-blue-800',
        idle: 'border-transparent text-zinc-500 hover:border-blue-200 hover:text-blue-800',
    },
    laravel: {
        active: 'border-red-600 text-red-700',
        idle: 'border-transparent text-zinc-500 hover:border-red-200 hover:text-red-700',
    },
    sito: {
        active: 'border-teal-600 text-teal-800',
        idle: 'border-transparent text-zinc-500 hover:border-teal-200 hover:text-teal-800',
    },
    database: {
        active: 'border-sky-600 text-sky-800',
        idle: 'border-transparent text-zinc-500 hover:border-sky-200 hover:text-sky-800',
    },
    sftp: {
        active: 'border-emerald-600 text-emerald-800',
        idle: 'border-transparent text-zinc-500 hover:border-emerald-200 hover:text-emerald-800',
    },
    accessi: {
        active: 'border-orange-500 text-orange-800',
        idle: 'border-transparent text-zinc-500 hover:border-orange-200 hover:text-orange-800',
    },
    pericolo: {
        active: 'border-red-600 text-red-700',
        idle: 'border-transparent text-zinc-500 hover:border-red-200 hover:text-red-700',
    },
};

const tabClass = (tab) => {
    const tone = tabTones[tab] ?? tabTones.generale;

    return currentTab.value === tab ? tone.active : tone.idle;
};

const sectionTone = {
    overview: 'rounded-xl border border-zinc-200 bg-white p-6 shadow-sm',
    meta: 'rounded-xl border border-zinc-200 bg-gradient-to-br from-zinc-50 to-white p-4 shadow-sm',
    runtime:
        'rounded-xl border border-teal-200 bg-gradient-to-br from-teal-50/90 to-white p-6 shadow-sm',
    runtimeDebug:
        'rounded-xl border border-amber-300 bg-gradient-to-br from-amber-50 to-white p-6 shadow-sm',
    siteEnv: 'rounded-xl border border-sky-200 bg-gradient-to-br from-sky-50/80 to-white p-6 shadow-sm',
    php: 'rounded-xl border border-orange-200 bg-gradient-to-br from-orange-50/70 to-white p-6 shadow-sm',
    boot: 'rounded-xl border border-zinc-300 bg-gradient-to-br from-zinc-50 to-white p-6 shadow-sm',
    database: 'rounded-xl border border-sky-200 bg-gradient-to-br from-sky-50/70 to-white p-6 shadow-sm',
    backup: 'rounded-xl border border-cyan-200 bg-gradient-to-br from-cyan-50/70 to-white p-6 shadow-sm',
    sftp: 'rounded-xl border border-emerald-200 bg-gradient-to-br from-emerald-50/70 to-white p-6 shadow-sm',
    access: 'rounded-xl border border-orange-200 bg-gradient-to-br from-orange-50/70 to-white p-6 shadow-sm',
    danger: 'min-w-0 rounded-xl border border-red-300 bg-gradient-to-br from-red-50 to-white p-6 shadow-sm',
};

const headingTone = {
    overview: 'text-zinc-900',
    runtime: 'text-teal-950',
    runtimeDebug: 'text-amber-950',
    siteEnv: 'text-sky-950',
    php: 'text-orange-950',
    boot: 'text-zinc-900',
    database: 'text-sky-950',
    backup: 'text-cyan-950',
    sftp: 'text-emerald-950',
    access: 'text-orange-950',
    danger: 'text-red-800',
};

const actionUrl = (path) => {
    const tab = currentTab.value;

    return tab === 'generale' ? path : `${path}?tab=${tab}`;
};

const credentials = computed(() => {
    const raw = page.props.flash?.revealed_credential ?? page.props.revealedCredential;

    if (!raw) {
        return [];
    }

    return Array.isArray(raw) ? raw : [raw];
});

const revealedForDomain = computed(() =>
    credentials.value.filter((item) => Number(item.domain_id) === Number(props.domain.id)),
);

const showCredentialAlert = ref(false);
const dismissedCredentialKey = ref('');

const credentialAlertKey = computed(() =>
    revealedForDomain.value
        .map((item) => `${item.kind}:${item.username}:${item.password}`)
        .join('|'),
);

watch(
    credentialAlertKey,
    (key) => {
        if (key !== '' && key !== dismissedCredentialKey.value) {
            showCredentialAlert.value = true;
        }
    },
    { immediate: true },
);

const closeCredentialAlert = () => {
    showCredentialAlert.value = false;
    dismissedCredentialKey.value = credentialAlertKey.value;
};

const domainInfra = computed(() =>
    props.infrastructures.find((infra) => infra.slug === props.domain.infra_slug),
);

const sftpEndpoint = computed(() => {
    const host = domainInfra.value?.sftp_public_host || domainInfra.value?.sftp_host;
    const port = domainInfra.value?.sftp_host_port;

    if (!host) {
        return '';
    }

    return port ? `${host}:${port}` : host;
});

const databaseForm = useForm({
    engine: 'mysql',
    infra_slug: props.domain.infra_slug,
});

const databaseUserForm = useForm({
    privilege: 'all',
});
const backupForm = useForm({});
const backupSettingsForm = useForm({
    backup_enabled: props.domain.backup_enabled !== false,
    backup_frequency: props.domain.backup_frequency || 'daily',
    backup_retain_days: props.domain.backup_retain_days || 14,
});
const importDumpForm = useForm({
    dump: null,
    confirm_overwrite: false,
    allow_overwrite_production: false,
});
const alignForm = useForm({
    source_domain_id: '',
    allow_overwrite_production: false,
});
const aliasForm = useForm({
    kind: 'alias',
    fqdn: '',
    subdomain: '',
    web_service: true,
    redirect_301: false,
    mail_service: false,
    sync_dns: false,
});

const createAlias = () => {
    aliasForm.post(actionUrl(`/domains/${props.domain.id}/aliases`), {
        preserveScroll: true,
        onSuccess: () => {
            aliasForm.reset();
            aliasForm.kind = 'alias';
            aliasForm.web_service = true;
            aliasForm.redirect_301 = false;
        },
    });
};

const deleteAlias = (aliasId) => {
    if (!window.confirm(t('domains.aliases.delete_confirm'))) {
        return;
    }

    useForm({}).delete(actionUrl(`/domains/${props.domain.id}/aliases/${aliasId}`), {
        preserveScroll: true,
    });
};

const createDatabaseBackup = () => {
    backupForm.post(actionUrl(`/domains/${props.domain.id}/backups/database`), { preserveScroll: true });
};

const importDatabaseDump = () => {
    importDumpForm.post(actionUrl(`/domains/${props.domain.id}/database/import`), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            importDumpForm.reset();
            importDumpForm.confirm_overwrite = false;
            importDumpForm.allow_overwrite_production = false;
        },
    });
};

const saveBackupSettings = () => {
    backupSettingsForm.post(actionUrl(`/domains/${props.domain.id}/backups/settings`), { preserveScroll: true });
};

const restoreDatabaseBackup = (backupId) => {
    useForm({}).post(actionUrl(`/domains/${props.domain.id}/backups/${backupId}/restore`), {
        preserveScroll: true,
    });
};

const alignFromDomain = () => {
    alignForm.post(actionUrl(`/domains/${props.domain.id}/align-from`), { preserveScroll: true });
};

const resyncMysqlForm = useForm({});

const resyncMysqlUsers = () => {
    resyncMysqlForm.post(actionUrl(`/domains/${props.domain.id}/database-users/resync`), { preserveScroll: true });
};

const resetDbUserPassword = (accountId) => {
    useForm({}).post(actionUrl(`/domains/${props.domain.id}/database-users/${accountId}/reset-password`), {
        preserveScroll: false,
    });
};

const deleteDbUser = (accountId) => {
    if (!window.confirm(t('domains.show.delete_db_user_confirm'))) {
        return;
    }

    useForm({}).delete(actionUrl(`/domains/${props.domain.id}/database-users/${accountId}`), { preserveScroll: true });
};

const sftpUserForm = useForm({});

const laravelForm = useForm({});

const alignEnvForm = useForm({});

const phpSettingsForm = useForm({
    memory_limit: props.phpSettings.memory_limit,
    upload_max_filesize: props.phpSettings.upload_max_filesize,
    post_max_size: props.phpSettings.post_max_size,
    max_execution_time: props.phpSettings.max_execution_time,
    max_input_time: props.phpSettings.max_input_time,
    artisan_memory_limit: props.phpSettings.artisan_memory_limit,
    deploy_now: false,
});

const fqdnForm = useForm({
    fqdn: props.domain.fqdn,
});

const deleteDomainForm = useForm({});

const selectedInfra = computed(() =>
    props.infrastructures.find((infra) => infra.slug === databaseForm.infra_slug),
);

const canCreateSelectedDatabase = computed(() => {
    if (!selectedInfra.value) {
        return false;
    }

    return databaseForm.engine === 'postgres' ? selectedInfra.value.can_postgres : selectedInfra.value.can_mysql;
});

const createDatabase = () => {
    databaseForm.post(actionUrl(`/domains/${props.domain.id}/database`));
};

const createDatabaseUser = () => {
    databaseUserForm.post(actionUrl(`/domains/${props.domain.id}/database-users`), { preserveScroll: true });
};

const createSftpUser = () => {
    sftpUserForm.post(actionUrl(`/domains/${props.domain.id}/sftp-users`), { preserveScroll: true });
};

const privilegeLabel = (privilege) => (privilege === 'select' ? t('common.privilege_select') : t('common.privilege_all'));

const installLaravel = () => {
    laravelForm.post(actionUrl(`/domains/${props.domain.id}/laravel`));
};

const alignBootEnv = () => {
    alignEnvForm.post(actionUrl(`/domains/${props.domain.id}/laravel/align-env`), { preserveScroll: true });
};

const savePhpSettings = () => {
    phpSettingsForm.post(actionUrl(`/domains/${props.domain.id}/php-settings`), { preserveScroll: true });
};

const deploySiteForm = useForm({});

const deploySite = () => {
    deploySiteForm.post(actionUrl(`/domains/${props.domain.id}/deploy`), { preserveScroll: true });
};

const siteEnvKey = ref('');
const siteEnvValue = ref('');

const siteEnvForm = useForm({
    entries: [],
});

const runtimeEnvValue = (key, fallback = '') => {
    const row = (props.siteHosting.variables || []).find((item) => item.key === key);
    if (!row || row.redacted || row.value === undefined || row.value === null || row.value === '') {
        return fallback;
    }

    return String(row.value);
};

const runtimeEnvForm = useForm({
    app_env: runtimeEnvValue('APP_ENV', 'production'),
    app_debug: ['true', '1', 'yes', 'on'].includes(String(runtimeEnvValue('APP_DEBUG', 'false')).toLowerCase()),
    log_level: runtimeEnvValue('LOG_LEVEL', 'info'),
    deploy: false,
});

const saveRuntimeEnv = (deploy = false) => {
    runtimeEnvForm.deploy = deploy;
    runtimeEnvForm.post(actionUrl(`/domains/${props.domain.id}/runtime-env`), { preserveScroll: true });
};

const saveSiteEnv = () => {
    const key = siteEnvKey.value.trim().toUpperCase();

    if (!key) {
        return;
    }

    siteEnvForm.entries = [{ key, value: siteEnvValue.value }];
    siteEnvForm.post(actionUrl(`/domains/${props.domain.id}/site-env`), {
        preserveScroll: true,
        onSuccess: () => {
            siteEnvKey.value = '';
            siteEnvValue.value = '';
        },
    });
};

const saveFqdn = () => {
    fqdnForm.put(actionUrl(`/domains/${props.domain.id}`), { preserveScroll: true });
};

const deleteDomain = () => {
    if (
        !window.confirm(
            t('domains.show.delete_confirm', { fqdn: props.domain.fqdn }),
        )
    ) {
        return;
    }

    deleteDomainForm.delete(`/domains/${props.domain.id}`);
};

const inputClass =
    'mt-1.5 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10';

const spazio = computed(() => props.domain.subscription);

const grantAccess = () => {
    accessForm.post(actionUrl(`/domains/${props.domain.id}/members`), { preserveScroll: true });
};

const revokeAccess = (memberId) => {
    if (!window.confirm(t('domains.show.revoke_confirm'))) {
        return;
    }

    useForm({}).delete(actionUrl(`/domains/${props.domain.id}/members/${memberId}`), { preserveScroll: true });
};

const updateMemberRole = (memberId, role) => {
    useForm({ role }).put(actionUrl(`/domains/${props.domain.id}/members/${memberId}`), { preserveScroll: true });
};
</script>

<template>
    <AppLayout
        :title="domain.fqdn"
        :description="t('domains.show.description', { space: spazio?.name || t('common.fallback_space'), customer: spazio?.customer?.name || domain.customer?.name || t('common.fallback_customer'), plan: spazio?.service_plan?.name || t('common.fallback_plan') })"
    >
        <template #actions>
            <Link
                v-if="canViewSubscription && domain.subscription_id"
                :href="`/subscriptions/${domain.subscription_id}`"
                class="text-sm text-zinc-600 hover:text-zinc-900"
            >
                {{ t('common.space') }}
            </Link>
        </template>

        <p
            v-if="!canMutateHosting"
            class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
        >
            {{ t('domains.show.readonly') }}
        </p>

        <p v-if="dokployAccessNote" class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950">
            {{ dokployAccessNote }}
        </p>

        <p class="mb-4 text-sm text-zinc-500">
            <Link
                v-if="canViewSubscription && domain.subscription_id"
                :href="`/subscriptions/${domain.subscription_id}`"
                class="hover:underline"
            >
                {{ spazio?.name || t('common.space') }}
            </Link>
            <span v-if="canViewSubscription && domain.subscription_id" class="mx-1.5 text-zinc-300">→</span>
            {{ domain.fqdn }}
        </p>

        <nav class="-mx-1 mb-6 flex min-w-0 flex-wrap gap-1 border-b border-neutral-200" :aria-label="t('common.sections')">
            <Link
                v-for="tab in tabs"
                :key="tab.id"
                :href="tabHref(tab.id)"
                preserve-scroll
                preserve-state
                class="border-b-2 px-3 py-2 text-sm font-medium transition-colors"
                :class="tabClass(tab.id)"
            >
                {{ tab.label }}
            </Link>
        </nav>

        <div v-show="currentTab === 'generale'" class="min-w-0 space-y-6">
            <section :class="sectionTone.overview">
                <h2 class="text-base font-semibold" :class="headingTone.overview">{{ t('domains.show.heading') }}</h2>
                <form
                    v-if="canEditFqdn"
                    class="mt-5 grid gap-4 sm:grid-cols-[1fr_auto]"
                    @submit.prevent="saveFqdn"
                >
                    <div>
                        <label class="block text-sm font-medium" :for="`fqdn-edit-${domain.id}`">{{ t('common.fqdn') }}</label>
                        <input
                            :id="`fqdn-edit-${domain.id}`"
                            v-model="fqdnForm.fqdn"
                            type="text"
                            required
                            :class="inputClass"
                        />
                        <p v-if="fqdnForm.errors.fqdn" class="mt-1 text-sm text-red-600">{{ fqdnForm.errors.fqdn }}</p>
                    </div>
                    <div class="flex items-end">
                        <button
                            type="submit"
                            class="rounded-lg border border-zinc-200 bg-white px-3.5 py-2 text-sm font-medium hover:bg-zinc-50 disabled:opacity-50"
                            :disabled="fqdnForm.processing"
                        >
                            {{ t('domains.show.save_fqdn') }}
                        </button>
                    </div>
                </form>
                <dl v-else class="mt-5">
                    <div class="rounded-lg bg-zinc-50 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ t('common.fqdn') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ domain.fqdn }}</dd>
                    </div>
                </dl>
            </section>

            <dl class="grid min-w-0 gap-4 sm:grid-cols-2">
                <div :class="sectionTone.meta">
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ t('common.infra') }}</dt>
                    <dd class="mt-2 text-sm font-medium">{{ domain.infra_slug }}</dd>
                </div>
                <div
                    class="rounded-xl border p-4 shadow-sm"
                    :class="isLaravelStack
                        ? 'border-red-100 bg-gradient-to-br from-red-50/80 to-white'
                        : 'border-teal-100 bg-gradient-to-br from-teal-50/80 to-white'"
                >
                    <dt
                        class="text-xs font-medium uppercase tracking-wide"
                        :class="isLaravelStack ? 'text-red-700/70' : 'text-teal-700/70'"
                    >
                        {{ t('common.stack') }}
                    </dt>
                    <dd
                        class="mt-2 text-sm font-medium"
                        :class="isLaravelStack ? 'text-red-950' : 'text-teal-950'"
                    >
                        {{ domain.stack_label || domain.stack || t('common.hosting_only') }}
                    </dd>
                </div>
                <div class="rounded-xl border border-orange-100 bg-gradient-to-br from-orange-50/70 to-white p-4 shadow-sm">
                    <dt class="text-xs font-medium uppercase tracking-wide text-orange-700/70">{{ t('common.customer') }}</dt>
                    <dd class="mt-2 text-sm font-medium text-orange-950">
                        {{ spazio?.customer?.name || domain.customer?.name || '—' }}
                    </dd>
                </div>
                <div
                    v-for="share in domain.storage_shares"
                    :key="share.id"
                    class="rounded-xl border border-emerald-100 bg-gradient-to-br from-emerald-50/70 to-white p-4 shadow-sm sm:col-span-2"
                >
                    <dt class="text-xs font-medium uppercase tracking-wide text-emerald-700/70">{{ t('common.storage') }}</dt>
                    <dd class="mt-2 break-all text-sm font-medium text-emerald-950">{{ share.path }}</dd>
                </div>
            </dl>
        </div>

        <div v-show="currentTab === 'hosts'" class="min-w-0 space-y-6">
            <p v-if="page.props.flash?.success && currentTab === 'hosts'" class="text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </p>

            <section class="rounded-xl border border-blue-100 bg-gradient-to-br from-blue-50/70 to-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-blue-950">{{ t('domains.aliases.heading') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ t('domains.aliases.help', { fqdn: domain.fqdn }) }}
                </p>

                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-blue-100 text-xs uppercase tracking-wide text-blue-800/70">
                            <tr>
                                <th class="px-2 py-2 font-medium">{{ t('common.fqdn') }}</th>
                                <th class="px-2 py-2 font-medium">{{ t('domains.aliases.kind') }}</th>
                                <th class="px-2 py-2 font-medium">{{ t('domains.aliases.flags') }}</th>
                                <th v-if="canMutateHosting" class="px-2 py-2 font-medium">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-zinc-100">
                                <td class="px-2 py-3 font-medium text-zinc-900">{{ domain.fqdn }}</td>
                                <td class="px-2 py-3 text-zinc-600">{{ t('domains.aliases.kind_primary') }}</td>
                                <td class="px-2 py-3 text-zinc-500">—</td>
                                <td v-if="canMutateHosting" class="px-2 py-3"></td>
                            </tr>
                            <tr
                                v-for="alias in domain.aliases || []"
                                :key="alias.id"
                                class="border-b border-zinc-100"
                            >
                                <td class="px-2 py-3">
                                    <div class="font-medium text-zinc-900">{{ alias.fqdn }}</div>
                                    <div class="text-xs text-zinc-500">
                                        {{ t('domains.aliases.for_primary', { fqdn: domain.fqdn }) }}
                                    </div>
                                </td>
                                <td class="px-2 py-3 text-zinc-600">
                                    {{ alias.kind === 'subdomain'
                                        ? t('domains.aliases.kind_subdomain')
                                        : t('domains.aliases.kind_alias') }}
                                </td>
                                <td class="px-2 py-3">
                                    <div class="flex flex-wrap gap-1.5 text-xs">
                                        <span
                                            v-if="alias.web_service"
                                            class="rounded-md bg-blue-100 px-1.5 py-0.5 text-blue-900"
                                        >{{ t('domains.aliases.flag_web') }}</span>
                                        <span
                                            v-if="alias.redirect_301"
                                            class="rounded-md bg-amber-100 px-1.5 py-0.5 text-amber-900"
                                        >{{ t('domains.aliases.flag_redirect') }}</span>
                                        <span
                                            v-if="alias.mail_service"
                                            class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-zinc-700"
                                        >{{ t('domains.aliases.flag_mail') }}</span>
                                        <span
                                            v-if="alias.sync_dns"
                                            class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-zinc-700"
                                        >{{ t('domains.aliases.flag_dns') }}</span>
                                    </div>
                                </td>
                                <td v-if="canMutateHosting" class="px-2 py-3">
                                    <button
                                        type="button"
                                        class="text-sm font-medium text-red-700 hover:text-red-900"
                                        @click="deleteAlias(alias.id)"
                                    >
                                        {{ t('common.delete') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!(domain.aliases || []).length" class="mt-3 text-sm text-zinc-500">
                        {{ t('domains.aliases.empty') }}
                    </p>
                </div>
            </section>

            <section
                v-if="canMutateHosting"
                class="rounded-xl border border-blue-100 bg-gradient-to-br from-blue-50/50 to-white p-5 shadow-sm"
            >
                <h2 class="text-base font-semibold text-blue-950">{{ t('domains.aliases.add_heading') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ t('domains.aliases.add_help') }}</p>

                <form class="mt-5 space-y-4" @submit.prevent="createAlias">
                    <fieldset class="grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-2 text-sm text-zinc-800">
                            <input v-model="aliasForm.kind" type="radio" value="alias" class="border-zinc-300" />
                            {{ t('domains.aliases.kind_alias') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-zinc-800">
                            <input v-model="aliasForm.kind" type="radio" value="subdomain" class="border-zinc-300" />
                            {{ t('domains.aliases.kind_subdomain') }}
                        </label>
                    </fieldset>

                    <div v-if="aliasForm.kind === 'alias'">
                        <label class="block text-sm font-medium text-zinc-700" for="alias-fqdn">
                            {{ t('domains.aliases.fqdn_label') }}
                        </label>
                        <input
                            id="alias-fqdn"
                            v-model="aliasForm.fqdn"
                            type="text"
                            placeholder="shop.example.net"
                            :class="inputClass"
                            class="mt-1"
                        />
                        <p class="mt-1 text-xs text-zinc-500">{{ t('domains.aliases.fqdn_help') }}</p>
                        <p v-if="aliasForm.errors.fqdn" class="mt-1 text-sm text-red-600">{{ aliasForm.errors.fqdn }}</p>
                    </div>

                    <div v-else>
                        <label class="block text-sm font-medium text-zinc-700" for="alias-subdomain">
                            {{ t('domains.aliases.subdomain_label') }}
                        </label>
                        <div class="mt-1 flex items-center gap-2">
                            <input
                                id="alias-subdomain"
                                v-model="aliasForm.subdomain"
                                type="text"
                                placeholder="app"
                                :class="inputClass"
                                class="min-w-0 flex-1"
                            />
                            <span class="shrink-0 text-sm text-zinc-500">.{{ domain.fqdn }}</span>
                        </div>
                        <p v-if="aliasForm.errors.subdomain" class="mt-1 text-sm text-red-600">{{ aliasForm.errors.subdomain }}</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex items-start gap-2 text-sm text-zinc-800">
                            <input v-model="aliasForm.web_service" type="checkbox" class="mt-0.5 rounded border-zinc-300" />
                            <span>
                                <span class="font-medium">{{ t('domains.aliases.flag_web') }}</span>
                                <span class="mt-0.5 block text-xs text-zinc-500">{{ t('domains.aliases.web_help') }}</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-zinc-800">
                            <input v-model="aliasForm.redirect_301" type="checkbox" class="mt-0.5 rounded border-zinc-300" />
                            <span>
                                <span class="font-medium">{{ t('domains.aliases.flag_redirect') }}</span>
                                <span class="mt-0.5 block text-xs text-zinc-500">{{ t('domains.aliases.redirect_help') }}</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-zinc-800">
                            <input v-model="aliasForm.mail_service" type="checkbox" class="mt-0.5 rounded border-zinc-300" />
                            <span>
                                <span class="font-medium">{{ t('domains.aliases.flag_mail') }}</span>
                                <span class="mt-0.5 block text-xs text-zinc-500">{{ t('domains.aliases.mail_help') }}</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-zinc-800">
                            <input v-model="aliasForm.sync_dns" type="checkbox" class="mt-0.5 rounded border-zinc-300" />
                            <span>
                                <span class="font-medium">{{ t('domains.aliases.flag_dns') }}</span>
                                <span class="mt-0.5 block text-xs text-zinc-500">{{ t('domains.aliases.dns_help') }}</span>
                            </span>
                        </label>
                    </div>

                    <p v-if="aliasForm.errors.web_service" class="text-sm text-red-600">{{ aliasForm.errors.web_service }}</p>
                    <p v-if="aliasForm.errors.kind" class="text-sm text-red-600">{{ aliasForm.errors.kind }}</p>

                    <button
                        type="submit"
                        class="rounded-lg bg-blue-700 px-3.5 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-50"
                        :disabled="aliasForm.processing"
                    >
                        {{ t('domains.aliases.submit') }}
                    </button>
                </form>
            </section>
        </div>

        <div v-show="currentTab === 'database'" class="min-w-0 space-y-6">
            <p v-if="page.props.flash?.success && currentTab === 'database'" class="text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </p>
            <p v-if="page.props.flash?.error && currentTab === 'database'" class="text-sm text-red-700">
                {{ page.props.flash.error }}
            </p>
            <section
                v-if="canMutateHosting && !domain.database_accounts?.length"
                :class="sectionTone.database"
            >
                <h2 class="text-base font-semibold" :class="headingTone.database">{{ t('domains.show.create_database') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ t('domains.show.create_database_help') }}
                </p>
                <form class="mt-5 grid gap-3 sm:grid-cols-2" @submit.prevent="createDatabase">
                    <div>
                        <label class="block text-sm font-medium" :for="`infra-${domain.id}`">{{ t('domains.create.infra_legend') }}</label>
                        <select
                            :id="`infra-${domain.id}`"
                            v-model="databaseForm.infra_slug"
                            required
                            :class="inputClass"
                        >
                            <option v-for="infra in infrastructures" :key="infra.id" :value="infra.slug">
                                {{ infra.slug }} · {{ infra.status }}
                            </option>
                        </select>
                        <p v-if="databaseForm.errors.infra_slug" class="mt-1 text-sm text-red-600">
                            {{ databaseForm.errors.infra_slug }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" :for="`engine-${domain.id}`">{{ t('common.engine') }}</label>
                        <select :id="`engine-${domain.id}`" v-model="databaseForm.engine" :class="inputClass">
                            <option value="mysql" :disabled="!selectedInfra?.can_mysql">{{ t('common.mysql') }}</option>
                            <option value="postgres" :disabled="!selectedInfra?.can_postgres">{{ t('common.postgres') }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <button
                            type="submit"
                            class="rounded-lg bg-sky-700 px-3.5 py-2 text-sm font-medium text-white hover:bg-sky-800 disabled:opacity-50"
                            :disabled="databaseForm.processing || !canCreateSelectedDatabase"
                        >
                            {{ t('domains.show.create_database') }}
                        </button>
                    </div>
                </form>
                <p v-if="selectedInfra && !canCreateSelectedDatabase" class="mt-2 text-sm text-amber-700">
                    {{ t('domains.show.engine_missing') }}
                </p>
                <p v-if="databaseForm.errors.engine" class="mt-1 text-sm text-red-600">{{ databaseForm.errors.engine }}</p>
            </section>

            <section v-else :class="sectionTone.database">
                <h2 class="text-base font-semibold" :class="headingTone.database">{{ t('common.database') }}</h2>
                <div v-for="account in domain.database_accounts" :key="account.id" class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-zinc-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">{{ t('common.name') }}</p>
                        <p class="mt-1 text-sm font-medium">{{ account.database_name }}</p>
                    </div>
                    <div class="rounded-lg bg-zinc-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">{{ t('common.host') }}</p>
                        <p class="mt-1 text-sm font-medium">{{ account.host }}:{{ account.port }}</p>
                    </div>
                    <div class="rounded-lg bg-zinc-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">{{ t('common.user') }}</p>
                        <p class="mt-1 text-sm font-medium">{{ account.username }}</p>
                    </div>
                    <div class="rounded-lg bg-zinc-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">{{ t('common.privileges') }}</p>
                        <p class="mt-1 text-sm font-medium">{{ privilegeLabel(account.privilege) }}</p>
                    </div>
                    <div class="rounded-lg bg-zinc-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">{{ t('common.engine') }}</p>
                        <p class="mt-1 text-sm font-medium">{{ account.engine }} · {{ account.infra_slug }}</p>
                    </div>
                </div>
            </section>

            <section :class="sectionTone.database">
                <h2 class="text-base font-semibold" :class="headingTone.database">{{ t('domains.show.db_users') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ t('domains.show.db_users_help') }}
                </p>
                <p v-if="canMutateHosting && domain.database_accounts?.length" class="mt-3 text-sm text-zinc-500">
                    {{ t('domains.show.resync_mysql_users_help') }}
                </p>
                <form
                    v-if="canMutateHosting && domain.database_accounts?.length"
                    class="mt-3"
                    @submit.prevent="resyncMysqlUsers"
                >
                    <button
                        type="submit"
                        class="rounded-lg border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-zinc-800 hover:bg-zinc-50 disabled:opacity-50"
                        :disabled="resyncMysqlForm.processing"
                    >
                        {{ t('domains.show.resync_mysql_users') }}
                    </button>
                </form>
                <ul v-if="domain.database_accounts?.length" class="mt-4 space-y-2">
                    <li
                        v-for="account in domain.database_accounts"
                        :key="`db-user-${account.id}`"
                        class="rounded-lg bg-zinc-50 px-3 py-2 text-sm"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <span class="font-medium">{{ account.username }}</span>
                                <span class="text-zinc-500">
                                    · {{ account.database_name }} · {{ privilegeLabel(account.privilege) }}
                                </span>
                            </div>
                            <div v-if="canMutateHosting && account.engine === 'mysql'" class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-md border border-neutral-300 bg-white px-2 py-1 text-xs font-medium text-zinc-800 hover:bg-zinc-100"
                                    @click="resetDbUserPassword(account.id)"
                                >
                                    {{ t('domains.show.reset_db_user_password') }}
                                </button>
                                <button
                                    v-if="domain.database_accounts.length > 1"
                                    type="button"
                                    class="rounded-md border border-red-200 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50"
                                    @click="deleteDbUser(account.id)"
                                >
                                    {{ t('domains.show.delete_db_user') }}
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-zinc-500">{{ t('domains.show.no_database') }}</p>
                <form
                    v-if="canMutateHosting"
                    class="mt-4 grid gap-3 sm:grid-cols-2"
                    @submit.prevent="createDatabaseUser"
                >
                    <div>
                        <label class="block text-sm font-medium" :for="`db-priv-${domain.id}`">{{ t('common.privileges') }}</label>
                        <select
                            :id="`db-priv-${domain.id}`"
                            v-model="databaseUserForm.privilege"
                            required
                            :class="inputClass"
                        >
                            <option value="all">{{ t('common.privilege_all') }}</option>
                            <option value="select">{{ t('common.privilege_select') }}</option>
                        </select>
                        <p v-if="databaseUserForm.errors.privilege" class="mt-1 text-sm text-red-600">
                            {{ databaseUserForm.errors.privilege }}
                        </p>
                    </div>
                    <div class="flex items-end">
                        <button
                            type="submit"
                            class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                            :disabled="databaseUserForm.processing || !domain.database_accounts?.length"
                        >
                            {{ t('domains.show.create_db_user') }}
                        </button>
                    </div>
                </form>
            </section>

            <section
                v-if="canMutateHosting && domain.database_accounts?.length"
                class="rounded-xl border border-cyan-300 bg-gradient-to-br from-cyan-50/80 to-white p-6 shadow-sm"
            >
                <h2 class="text-base font-semibold text-cyan-950">{{ t('domains.show.import_dump') }}</h2>
                <p class="mt-1 text-sm text-zinc-600">{{ t('domains.show.import_dump_help') }}</p>
                <form class="mt-4 grid gap-3" @submit.prevent="importDatabaseDump">
                    <div>
                        <label class="block text-sm font-medium" :for="`import-dump-${domain.id}`">
                            {{ t('domains.show.import_dump_file') }}
                        </label>
                        <input
                            :id="`import-dump-${domain.id}`"
                            type="file"
                            accept=".sql,.gz,application/sql,application/gzip,text/plain"
                            class="mt-1 block w-full text-sm text-zinc-700 file:mr-3 file:rounded-lg file:border-0 file:bg-cyan-700 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-cyan-800"
                            @change="importDumpForm.dump = $event.target.files?.[0] ?? null"
                        />
                        <p v-if="importDumpForm.errors.dump" class="mt-1 text-sm text-red-600">
                            {{ importDumpForm.errors.dump }}
                        </p>
                    </div>
                    <label class="flex items-start gap-2 text-sm text-zinc-800">
                        <input
                            v-model="importDumpForm.confirm_overwrite"
                            type="checkbox"
                            class="mt-0.5 rounded border-zinc-300"
                            required
                        />
                        <span>{{ t('domains.show.import_dump_confirm') }}</span>
                    </label>
                    <p v-if="importDumpForm.errors.confirm_overwrite" class="text-sm text-red-600">
                        {{ importDumpForm.errors.confirm_overwrite }}
                    </p>
                    <label class="flex items-start gap-2 text-sm text-zinc-800">
                        <input
                            v-model="importDumpForm.allow_overwrite_production"
                            type="checkbox"
                            class="mt-0.5 rounded border-zinc-300"
                        />
                        <span>{{ t('domains.show.import_dump_production') }}</span>
                    </label>
                    <p v-if="importDumpForm.errors.allow_overwrite_production" class="text-sm text-red-600">
                        {{ importDumpForm.errors.allow_overwrite_production }}
                    </p>
                    <div>
                        <button
                            type="submit"
                            class="rounded-lg bg-cyan-700 px-3.5 py-2 text-sm font-medium text-white hover:bg-cyan-800 disabled:opacity-50"
                            :disabled="importDumpForm.processing || !importDumpForm.dump || !importDumpForm.confirm_overwrite"
                        >
                            {{ t('domains.show.import_dump_submit') }}
                        </button>
                    </div>
                </form>
            </section>

            <section
                v-if="canMutateHosting && domain.database_accounts?.length"
                :class="sectionTone.backup"
            >
                <h2 class="text-base font-semibold" :class="headingTone.backup">{{ t('domains.show.backups') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ t('domains.show.backups_help') }}</p>

                <form class="mt-4 grid gap-3 sm:grid-cols-2" @submit.prevent="saveBackupSettings">
                    <label class="flex items-center gap-2 text-sm text-zinc-800 sm:col-span-2">
                        <input v-model="backupSettingsForm.backup_enabled" type="checkbox" class="rounded border-zinc-300" />
                        {{ t('domains.show.backup_enabled') }}
                    </label>
                    <div>
                        <label class="block text-sm font-medium" :for="`backup-freq-${domain.id}`">
                            {{ t('domains.show.backup_frequency') }}
                        </label>
                        <select
                            :id="`backup-freq-${domain.id}`"
                            v-model="backupSettingsForm.backup_frequency"
                            :class="inputClass"
                        >
                            <option value="daily">{{ t('domains.show.backup_frequency_daily') }}</option>
                            <option value="weekly">{{ t('domains.show.backup_frequency_weekly') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" :for="`backup-retain-${domain.id}`">
                            {{ t('domains.show.backup_retain_days') }}
                        </label>
                        <input
                            :id="`backup-retain-${domain.id}`"
                            v-model.number="backupSettingsForm.backup_retain_days"
                            type="number"
                            min="1"
                            max="365"
                            :class="inputClass"
                        />
                    </div>
                    <p v-if="domain.backup_last_ran_at" class="text-sm text-zinc-500 sm:col-span-2">
                        {{ t('domains.show.backup_last_ran') }}: {{ domain.backup_last_ran_at }}
                    </p>
                    <div class="sm:col-span-2">
                        <button
                            type="submit"
                            class="rounded-lg border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-zinc-800 hover:bg-zinc-50 disabled:opacity-50"
                            :disabled="backupSettingsForm.processing"
                        >
                            {{ t('domains.show.backup_settings_save') }}
                        </button>
                    </div>
                </form>

                <form class="mt-4" @submit.prevent="createDatabaseBackup">
                    <button
                        type="submit"
                        class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="backupForm.processing"
                    >
                        {{ t('domains.show.create_db_backup') }}
                    </button>
                    <p v-if="backupForm.errors.backup" class="mt-2 text-sm text-red-600">{{ backupForm.errors.backup }}</p>
                </form>
                <ul v-if="domain.backups?.length" class="mt-4 space-y-2">
                    <li
                        v-for="backup in domain.backups"
                        :key="backup.id"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-zinc-50 px-3 py-2 text-sm"
                    >
                        <span>
                            #{{ backup.id }} · {{ backup.kind }} · {{ backup.status }}
                            <span v-if="backup.bytes" class="text-zinc-500"> · {{ backup.bytes }} B</span>
                        </span>
                        <button
                            v-if="backup.status === 'completed' && backup.kind === 'database'"
                            type="button"
                            class="rounded-md border border-neutral-300 bg-white px-2 py-1 text-xs font-medium text-zinc-800 hover:bg-zinc-100"
                            @click="restoreDatabaseBackup(backup.id)"
                        >
                            {{ t('domains.show.restore_db_backup') }}
                        </button>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-zinc-500">{{ t('domains.show.no_backups') }}</p>

                <div v-if="alignTargets.length" class="mt-6 border-t border-neutral-100 pt-4">
                    <h3 class="text-sm font-semibold">{{ t('domains.show.align_from') }}</h3>
                    <p class="mt-1 text-sm text-zinc-500">{{ t('domains.show.align_from_help') }}</p>
                    <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="alignFromDomain">
                        <div>
                            <label class="block text-sm font-medium" :for="`align-source-${domain.id}`">
                                {{ t('domains.show.align_source') }}
                            </label>
                            <select
                                :id="`align-source-${domain.id}`"
                                v-model="alignForm.source_domain_id"
                                required
                                :class="inputClass"
                            >
                                <option value="" disabled>{{ t('common.select') }}</option>
                                <option v-for="target in alignTargets" :key="target.id" :value="target.id">
                                    {{ target.fqdn }}
                                </option>
                            </select>
                            <p v-if="alignForm.errors.source_domain_id" class="mt-1 text-sm text-red-600">
                                {{ alignForm.errors.source_domain_id }}
                            </p>
                            <p v-if="alignForm.errors.align" class="mt-1 text-sm text-red-600">{{ alignForm.errors.align }}</p>
                        </div>
                        <div class="flex flex-col justify-end gap-2">
                            <label class="flex items-center gap-2 text-sm text-zinc-700">
                                <input v-model="alignForm.allow_overwrite_production" type="checkbox" class="rounded border-zinc-300" />
                                {{ t('domains.show.allow_overwrite_production') }}
                            </label>
                            <button
                                type="submit"
                                class="rounded-lg border border-amber-300 bg-amber-50 px-3.5 py-2 text-sm font-medium text-amber-950 hover:bg-amber-100 disabled:opacity-50"
                                :disabled="alignForm.processing || !alignForm.source_domain_id"
                            >
                                {{ t('domains.show.align_submit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div v-show="currentTab === 'sftp'" class="min-w-0 space-y-6">
            <section :class="sectionTone.sftp">
                <h2 class="text-base font-semibold" :class="headingTone.sftp">{{ t('common.sftp') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ t('domains.show.sftp_help') }}
                </p>
                <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-zinc-50 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ t('common.host') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ sftpEndpoint || '—' }}</dd>
                    </div>
                    <div
                        v-for="user in domain.sftp_users"
                        :key="user.id"
                        class="contents"
                    >
                        <div class="rounded-lg bg-zinc-50 px-3 py-2">
                            <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ t('common.user') }}</dt>
                            <dd class="mt-1 text-sm font-medium">{{ user.username }}</dd>
                        </div>
                        <div class="rounded-lg bg-zinc-50 px-3 py-2 sm:col-span-2">
                            <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ t('common.home') }}</dt>
                            <dd class="mt-1 break-all text-sm font-medium">{{ user.home_path }}</dd>
                        </div>
                    </div>
                </dl>
                <p v-if="!domain.sftp_users?.length" class="mt-4 text-sm text-zinc-500">{{ t('domains.show.no_sftp') }}</p>
                <form v-if="canMutateHosting" class="mt-5" @submit.prevent="createSftpUser">
                    <p v-if="sftpUserForm.errors.username" class="mb-2 text-sm text-red-600">
                        {{ sftpUserForm.errors.username }}
                    </p>
                    <button
                        type="submit"
                        class="rounded-lg bg-emerald-700 px-3.5 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-50"
                        :disabled="sftpUserForm.processing"
                    >
                        {{ t('domains.show.create_sftp') }}
                    </button>
                </form>
            </section>
        </div>

        <div v-show="isAppTab" class="min-w-0 space-y-6">
            <p v-if="page.props.flash?.success && isAppTab" class="text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </p>

            <LaravelToolkitPanel
                v-if="isLaravelStack"
                :domain="domain"
                :deploy-preset="laravelDeployPreset"
                :attach-processing="laravelForm.processing"
                :attach-error="laravelForm.errors.attach_laravel || ''"
                :can-open-dokploy="canOpenDokploy"
                :can-mutate-hosting="canMutateHosting"
                @attach="installLaravel"
            />
            <StackToolkitPanel
                v-else
                :domain="domain"
                :stack-presets="stackPresets"
                :attach-processing="laravelForm.processing"
                :attach-error="laravelForm.errors.attach_laravel || ''"
                :can-open-dokploy="canOpenDokploy"
                :can-mutate-hosting="canMutateHosting"
                @attach="installLaravel"
            />

            <section
                v-if="siteHosting.available && canMutateHosting"
                :class="runtimeEnvForm.app_debug ? sectionTone.runtimeDebug : sectionTone.runtime"
            >
                <h2
                    class="text-base font-semibold"
                    :class="runtimeEnvForm.app_debug ? headingTone.runtimeDebug : headingTone.runtime"
                >
                    {{ t('domains.show.runtime_env') }}
                </h2>
                <p class="mt-1 text-sm text-zinc-600">{{ t('domains.show.runtime_env_help') }}</p>
                <p
                    v-if="runtimeEnvForm.app_debug"
                    class="mt-2 inline-flex rounded-md bg-amber-100 px-2 py-1 text-xs font-medium text-amber-900"
                >
                    {{ t('domains.show.runtime_debug_badge') }}
                </p>
                <form class="mt-4 grid gap-3 sm:grid-cols-3" @submit.prevent="saveRuntimeEnv(false)">
                    <div>
                        <label class="block text-sm font-medium" :for="`runtime-env-${domain.id}`">
                            {{ t('domains.show.runtime_app_env') }}
                        </label>
                        <select
                            :id="`runtime-env-${domain.id}`"
                            v-model="runtimeEnvForm.app_env"
                            :class="inputClass"
                        >
                            <option value="local">local (dev)</option>
                            <option value="staging">staging (test)</option>
                            <option value="production">production</option>
                            <option value="testing">testing</option>
                        </select>
                        <p v-if="runtimeEnvForm.errors.app_env" class="mt-1 text-sm text-red-600">
                            {{ runtimeEnvForm.errors.app_env }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" :for="`runtime-log-${domain.id}`">
                            {{ t('domains.show.runtime_log_level') }}
                        </label>
                        <select
                            :id="`runtime-log-${domain.id}`"
                            v-model="runtimeEnvForm.log_level"
                            :class="inputClass"
                        >
                            <option value="debug">debug</option>
                            <option value="info">info</option>
                            <option value="warning">warning</option>
                            <option value="error">error</option>
                        </select>
                        <p v-if="runtimeEnvForm.errors.log_level" class="mt-1 text-sm text-red-600">
                            {{ runtimeEnvForm.errors.log_level }}
                        </p>
                    </div>
                    <div class="flex flex-col justify-end gap-2">
                        <label class="flex items-center gap-2 text-sm text-zinc-800">
                            <input v-model="runtimeEnvForm.app_debug" type="checkbox" class="rounded border-zinc-300" />
                            {{ t('domains.show.runtime_app_debug') }}
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2 sm:col-span-3">
                        <button
                            type="submit"
                            class="rounded-lg border border-teal-300 bg-white px-3.5 py-2 text-sm font-medium text-teal-900 hover:bg-teal-50 disabled:opacity-50"
                            :disabled="runtimeEnvForm.processing"
                        >
                            {{ t('domains.show.runtime_save') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-teal-700 px-3.5 py-2 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-50"
                            :disabled="runtimeEnvForm.processing"
                            @click="saveRuntimeEnv(true)"
                        >
                            {{ t('domains.show.runtime_save_deploy') }}
                        </button>
                    </div>
                    <p v-if="runtimeEnvForm.errors.site_env" class="text-sm text-red-600 sm:col-span-3">
                        {{ runtimeEnvForm.errors.site_env }}
                    </p>
                </form>
            </section>

            <section
                v-if="siteHosting.available"
                :class="sectionTone.siteEnv"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold" :class="headingTone.siteEnv">{{ t('domains.show.site_env') }}</h2>
                        <p class="mt-1 text-sm text-zinc-600">
                            {{ t('domains.show.site_env_help') }}
                        </p>
                    </div>
                    <button
                        v-if="canMutateHosting"
                        type="button"
                        class="rounded-lg bg-sky-700 px-3.5 py-2 text-sm font-medium text-white hover:bg-sky-800 disabled:opacity-50"
                        :disabled="deploySiteForm.processing"
                        @click="deploySite"
                    >
                        {{ t('domains.show.deploy_site') }}
                    </button>
                </div>
                <p v-if="siteHosting.error" class="mt-3 text-sm text-red-600">{{ siteHosting.error }}</p>
                <p v-if="deploySiteForm.errors.deploy" class="mt-3 text-sm text-red-600">{{ deploySiteForm.errors.deploy }}</p>
                <div v-if="siteHosting.git" class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg bg-zinc-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">{{ t('domains.show.git_source') }}</p>
                        <p class="mt-1 text-sm font-medium">{{ siteHosting.git.source_type || '—' }}</p>
                    </div>
                    <div class="rounded-lg bg-zinc-50 px-3 py-2 sm:col-span-2">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">{{ t('common.repository') }}</p>
                        <p class="mt-1 break-all text-sm font-medium">{{ siteHosting.git.repository || '—' }}</p>
                    </div>
                    <div class="rounded-lg bg-zinc-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">{{ t('common.branch') }}</p>
                        <p class="mt-1 text-sm font-medium">{{ siteHosting.git.branch || '—' }}</p>
                    </div>
                </div>
                <p v-else-if="!siteHosting.error" class="mt-5 text-sm text-zinc-500">
                    {{ t('domains.show.git_missing') }}
                </p>

                <div v-if="siteHosting.variables.length" class="mt-5 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-500">
                                <th class="py-2 pr-4 font-medium">{{ t('common.key') }}</th>
                                <th class="py-2 font-medium">{{ t('common.value') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in siteHosting.variables"
                                :key="row.key"
                                class="border-b border-zinc-100"
                            >
                                <td class="py-2 pr-4 font-mono text-xs">{{ row.key }}</td>
                                <td class="py-2 font-mono text-xs break-all">
                                    <span v-if="row.redacted" class="text-zinc-500">{{ t('domains.show.redacted') }}</span>
                                    <span v-else>{{ row.value }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else-if="!siteHosting.error" class="mt-5 text-sm text-zinc-500">{{ t('domains.show.no_env') }}</p>
                <form v-if="canMutateHosting" class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="saveSiteEnv">
                    <div>
                        <label class="block text-sm font-medium" :for="`site-env-key-${domain.id}`">{{ t('common.key') }}</label>
                        <input
                            :id="`site-env-key-${domain.id}`"
                            v-model="siteEnvKey"
                            type="text"
                            required
                            placeholder="FEATURE_FLAG"
                            :class="inputClass"
                        />
                        <p v-if="siteEnvForm.errors['entries.0.key']" class="mt-1 text-sm text-red-600">
                            {{ siteEnvForm.errors['entries.0.key'] }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" :for="`site-env-val-${domain.id}`">{{ t('common.value') }}</label>
                        <input
                            :id="`site-env-val-${domain.id}`"
                            v-model="siteEnvValue"
                            type="text"
                            :class="inputClass"
                        />
                        <p class="mt-1 text-xs text-zinc-500">{{ t('domains.show.secret_blank') }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <button
                            type="submit"
                            class="rounded-lg border border-zinc-200 bg-white px-3.5 py-2 text-sm font-medium hover:bg-zinc-50 disabled:opacity-50"
                            :disabled="siteEnvForm.processing"
                        >
                            {{ t('domains.show.save_env_key') }}
                        </button>
                    </div>
                    <p v-if="siteEnvForm.errors.site_env" class="sm:col-span-2 text-sm text-red-600">
                        {{ siteEnvForm.errors.site_env }}
                    </p>
                </form>
            </section>

            <section
                v-if="phpSettingsApplicable"
                :class="sectionTone.php"
            >
                <h2 class="text-base font-semibold" :class="headingTone.php">{{ t('domains.show.php_heading') }}</h2>
                <p class="mt-1 text-sm text-zinc-600">
                    {{ t('domains.show.php_help') }}
                    <strong class="font-medium text-orange-900">{{ t('domains.show.php_deploy_note') }}</strong>
                    {{ t('domains.show.php_deploy_note_rest') }}
                </p>
                <form v-if="canMutateHosting" class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="savePhpSettings">
                    <div>
                        <label class="block text-sm font-medium" :for="`php-mem-${domain.id}`">memory_limit (web)</label>
                        <input
                            :id="`php-mem-${domain.id}`"
                            v-model="phpSettingsForm.memory_limit"
                            type="text"
                            required
                            :class="inputClass"
                        />
                        <p class="mt-1 text-xs text-zinc-500">Env: PHP_MEMORY_LIMIT</p>
                        <p v-if="phpSettingsForm.errors.memory_limit" class="mt-1 text-sm text-red-600">
                            {{ phpSettingsForm.errors.memory_limit }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" :for="`php-artisan-${domain.id}`">{{ t('domains.show.artisan_memory') }}</label>
                        <input
                            :id="`php-artisan-${domain.id}`"
                            v-model="phpSettingsForm.artisan_memory_limit"
                            type="text"
                            required
                            :class="inputClass"
                        />
                        <p class="mt-1 text-xs text-zinc-500">Env: RUNTS_SYNC_MEMORY_LIMIT, ARTISAN_MEMORY_LIMIT</p>
                        <p v-if="phpSettingsForm.errors.artisan_memory_limit" class="mt-1 text-sm text-red-600">
                            {{ phpSettingsForm.errors.artisan_memory_limit }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" :for="`php-upload-${domain.id}`">upload_max_filesize</label>
                        <input
                            :id="`php-upload-${domain.id}`"
                            v-model="phpSettingsForm.upload_max_filesize"
                            type="text"
                            required
                            :class="inputClass"
                        />
                        <p class="mt-1 text-xs text-zinc-500">Env: UPLOAD_MAX_FILESIZE</p>
                        <p v-if="phpSettingsForm.errors.upload_max_filesize" class="mt-1 text-sm text-red-600">
                            {{ phpSettingsForm.errors.upload_max_filesize }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" :for="`php-post-${domain.id}`">post_max_size</label>
                        <input
                            :id="`php-post-${domain.id}`"
                            v-model="phpSettingsForm.post_max_size"
                            type="text"
                            required
                            :class="inputClass"
                        />
                        <p class="mt-1 text-xs text-zinc-500">Env: POST_MAX_SIZE</p>
                        <p v-if="phpSettingsForm.errors.post_max_size" class="mt-1 text-sm text-red-600">
                            {{ phpSettingsForm.errors.post_max_size }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" :for="`php-exec-${domain.id}`">max_execution_time (s)</label>
                        <input
                            :id="`php-exec-${domain.id}`"
                            v-model.number="phpSettingsForm.max_execution_time"
                            type="number"
                            min="1"
                            required
                            :class="inputClass"
                        />
                        <p class="mt-1 text-xs text-zinc-500">Env: MAX_EXECUTION_TIME</p>
                        <p v-if="phpSettingsForm.errors.max_execution_time" class="mt-1 text-sm text-red-600">
                            {{ phpSettingsForm.errors.max_execution_time }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" :for="`php-input-${domain.id}`">max_input_time (s)</label>
                        <input
                            :id="`php-input-${domain.id}`"
                            v-model.number="phpSettingsForm.max_input_time"
                            type="number"
                            min="1"
                            required
                            :class="inputClass"
                        />
                        <p class="mt-1 text-xs text-zinc-500">Env: MAX_INPUT_TIME</p>
                        <p v-if="phpSettingsForm.errors.max_input_time" class="mt-1 text-sm text-red-600">
                            {{ phpSettingsForm.errors.max_input_time }}
                        </p>
                    </div>
                    <div class="sm:col-span-2 flex flex-wrap items-center gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-zinc-700">
                            <input v-model="phpSettingsForm.deploy_now" type="checkbox" class="rounded border-zinc-300" />
                            {{ t('domains.show.deploy_now') }}
                        </label>
                        <button
                            type="submit"
                            class="rounded-lg bg-orange-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-orange-700 disabled:opacity-50"
                            :disabled="phpSettingsForm.processing"
                        >
                            {{ t('domains.show.save_php') }}
                        </button>
                    </div>
                    <p v-if="phpSettingsForm.errors.php_settings" class="sm:col-span-2 text-sm text-red-600">
                        {{ phpSettingsForm.errors.php_settings }}
                    </p>
                </form>
                <dl v-else class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-zinc-50 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">memory_limit</dt>
                        <dd class="mt-1 text-sm font-medium">{{ phpSettings.memory_limit }}</dd>
                    </div>
                    <div class="rounded-lg bg-zinc-50 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ t('domains.show.artisan_cli') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ phpSettings.artisan_memory_limit }}</dd>
                    </div>
                </dl>
            </section>

            <section
                v-if="isLaravelStack && canMutateHosting"
                :class="sectionTone.boot"
            >
                <h2 class="text-base font-semibold" :class="headingTone.boot">{{ t('domains.show.boot_env') }}</h2>
                <p class="mt-1 text-sm text-zinc-600">
                    {{ t('domains.show.boot_env_help') }}
                </p>
                <p v-if="alignEnvForm.errors.align_env" class="mt-3 text-sm text-red-600">
                    {{ alignEnvForm.errors.align_env }}
                </p>
                <form class="mt-5" @submit.prevent="alignBootEnv">
                    <button
                        type="submit"
                        class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="alignEnvForm.processing || !domain.dokploy_application"
                    >
                        {{ t('domains.show.align_boot_env') }}
                    </button>
                </form>
            </section>
        </div>

        <div v-show="currentTab === 'accessi'" class="min-w-0 space-y-6">
            <section :class="sectionTone.access">
                <h2 class="text-base font-semibold" :class="headingTone.access">{{ t('domains.show.access') }}</h2>
                <p class="mt-1 text-sm text-zinc-600">
                    {{ t('domains.show.access_help') }}
                </p>
                <ul v-if="domainMembers.length" class="mt-4 divide-y divide-orange-100 rounded-lg border border-orange-100 bg-white/70">
                    <li
                        v-for="member in domainMembers"
                        :key="member.id"
                        class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm"
                    >
                        <span>{{ member.name }} · {{ member.email }}</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <select
                                :value="member.role"
                                class="rounded-lg border border-zinc-200 px-2 py-1 text-sm"
                                @change="updateMemberRole(member.id, $event.target.value)"
                            >
                                <option v-for="opt in roleOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                            <button
                                type="button"
                                class="text-red-700 hover:underline"
                                @click="revokeAccess(member.id)"
                            >
                                {{ t('common.remove') }}
                            </button>
                        </div>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-zinc-500">{{ t('domains.show.no_members') }}</p>
                <form class="mt-5 grid gap-3 sm:grid-cols-2" @submit.prevent="grantAccess">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium" for="access-email">{{ t('common.email') }}</label>
                        <input id="access-email" v-model="accessForm.email" type="email" required :class="inputClass" />
                        <p v-if="accessForm.errors.email" class="mt-1 text-sm text-red-600">{{ accessForm.errors.email }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" for="access-name">{{ t('domains.show.name_new_user') }}</label>
                        <input id="access-name" v-model="accessForm.name" type="text" :class="inputClass" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium" for="access-role">{{ t('common.role') }}</label>
                        <select id="access-role" v-model="accessForm.role" :class="inputClass">
                            <option v-for="opt in roleOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                        <p v-if="accessForm.errors.role" class="mt-1 text-sm text-red-600">{{ accessForm.errors.role }}</p>
                    </div>
                    <div class="flex items-end sm:col-span-2">
                        <button
                            type="submit"
                            class="rounded-lg bg-orange-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-orange-700 disabled:opacity-50"
                            :disabled="accessForm.processing"
                        >
                            {{ t('common.invite') }}
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <section
            v-show="currentTab === 'pericolo'"
            :class="sectionTone.danger"
        >
            <h2 class="text-base font-semibold" :class="headingTone.danger">{{ t('domains.show.delete_heading') }}</h2>
            <p class="mt-1 mb-5 text-sm text-zinc-500">
                {{ t('domains.show.delete_help') }}
            </p>
            <p v-if="deleteDomainForm.errors.domain" class="mb-4 text-sm text-red-600">
                {{ deleteDomainForm.errors.domain }}
            </p>
            <button
                type="button"
                class="rounded-lg border border-red-200 bg-red-50 px-3.5 py-2 text-sm font-medium text-red-800 hover:bg-red-100 disabled:opacity-50"
                :disabled="deleteDomainForm.processing"
                @click="deleteDomain"
            >
                {{ t('common.delete') }}
            </button>
        </section>

        <div
            v-if="showCredentialAlert && revealedForDomain.length"
            class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/50 p-4"
            role="alertdialog"
            aria-modal="true"
            :aria-label="t('domains.show.password_once')"
            @click.self="closeCredentialAlert"
        >
            <div class="w-full max-w-md rounded-xl border border-neutral-200 bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-zinc-950">{{ t('domains.show.password_once') }}</h3>
                <ul class="mt-4 space-y-3">
                    <li v-for="(item, index) in revealedForDomain" :key="`alert-${item.kind}-${index}`">
                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">
                            {{ item.kind === 'database' ? t('common.database') : t('common.sftp') }}
                        </p>
                        <p class="mt-1 font-mono text-sm break-all text-zinc-900">
                            {{ item.username }} / {{ item.password }}
                        </p>
                        <p v-if="item.kind === 'sftp' && sftpEndpoint" class="mt-1 text-xs text-zinc-500">
                            {{ t('domains.show.host_endpoint', { endpoint: sftpEndpoint }) }}
                        </p>
                    </li>
                </ul>
                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800"
                        @click="closeCredentialAlert"
                    >
                        {{ t('common.ok') }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
