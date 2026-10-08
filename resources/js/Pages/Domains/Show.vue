<!-- Show.vue — Show component.

  exports: defineProps
  used_by: none
  rules:   Env di avvio copy says database session and a Secure cookie on this host. Do not claim file sessions.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
           grok-4.7 | cursor | 2026-09-22 | s_20260922_session_cookie | Describe database session and host cookie
           composer-2.5-fast | cursor | 2026-09-24 | s_domain_php | Card PHP on Generale tab
           composer-2.5-fast | cursor | 2026-09-25 | s_domain_site_env | Env sito, deploy, git read-only on Generale
           composer-2.5-fast | cursor | 2026-09-25 | s_access_mail | Invita button + copy for email invite flow
-->

<script setup>
import { computed, ref } from 'vue';
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
});

const isLaravelStack = computed(() => (props.domain.stack || 'none') === 'laravel');

const tabs = computed(() => {
    const base = [
        { id: 'generale', label: t('domains.tabs.general') },
        { id: 'database', label: t('domains.tabs.database') },
        { id: 'sftp', label: t('domains.tabs.sftp') },
    ];

    if (isLaravelStack.value) {
        base.push({ id: 'laravel', label: t('domains.tabs.laravel') });
    } else {
        base.push({ id: 'sito', label: t('domains.tabs.site') });
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

const tabHref = (tab) => {
    const base = `/domains/${props.domain.id}`;

    return tab === 'generale' ? base : `${base}?tab=${tab}`;
};

const tabClass = (tab) =>
    currentTab.value === tab
        ? 'border-zinc-900 text-zinc-900'
        : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-800';

const actionUrl = (path) => {
    const tab = currentTab.value;

    return tab === 'generale' ? path : `${path}?tab=${tab}`;
};

const credentials = computed(() => {
    const raw = page.props.revealedCredential;

    if (!raw) {
        return [];
    }

    return Array.isArray(raw) ? raw : [raw];
});

const revealedForDomain = computed(() =>
    credentials.value.filter((item) => item.domain_id === props.domain.id),
);

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
    accessForm.post(`/domains/${props.domain.id}/members`, { preserveScroll: true });
};

const revokeAccess = (memberId) => {
    if (!window.confirm(t('domains.show.revoke_confirm'))) {
        return;
    }

    useForm({}).delete(`/domains/${props.domain.id}/members/${memberId}`, { preserveScroll: true });
};

const updateMemberRole = (memberId, role) => {
    useForm({ role }).put(`/domains/${props.domain.id}/members/${memberId}`, { preserveScroll: true });
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

        <div
            v-if="revealedForDomain.length"
            class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
        >
            <p class="font-medium">{{ t('domains.show.password_once') }}</p>
            <ul class="mt-2 space-y-2">
                <li v-for="(item, index) in revealedForDomain" :key="`${item.kind}-${index}`">
                    <span class="text-xs uppercase tracking-wide text-amber-800">
                        {{ item.kind === 'database' ? t('common.database') : t('common.sftp') }}
                    </span>
                    <p class="mt-0.5 font-mono break-all">{{ item.username }} / {{ item.password }}</p>
                    <p v-if="item.kind === 'sftp' && sftpEndpoint" class="mt-0.5 text-xs text-amber-800">
                        {{ t('domains.show.host_endpoint', { endpoint: sftpEndpoint }) }}
                    </p>
                </li>
            </ul>
        </div>

        <nav class="-mx-1 mb-6 flex min-w-0 flex-wrap gap-1 border-b border-neutral-200" :aria-label="t('common.sections')">
            <Link
                v-for="tab in tabs"
                :key="tab.id"
                :href="tabHref(tab.id)"
                preserve-scroll
                preserve-state
                class="border-b-2 px-3 py-2 text-sm font-medium"
                :class="tabClass(tab.id)"
            >
                {{ tab.label }}
            </Link>
        </nav>

        <div v-show="currentTab === 'generale'" class="min-w-0 space-y-6">
            <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">{{ t('domains.show.heading') }}</h2>
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
                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ t('common.infra') }}</dt>
                    <dd class="mt-2 text-sm font-medium">{{ domain.infra_slug }}</dd>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ t('common.stack') }}</dt>
                    <dd class="mt-2 text-sm font-medium">{{ domain.stack_label || domain.stack || t('common.hosting_only') }}</dd>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ t('common.customer') }}</dt>
                    <dd class="mt-2 text-sm font-medium">
                        {{ spazio?.customer?.name || domain.customer?.name || '—' }}
                    </dd>
                </div>
                <div
                    v-for="share in domain.storage_shares"
                    :key="share.id"
                    class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm sm:col-span-2"
                >
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ t('common.storage') }}</dt>
                    <dd class="mt-2 break-all text-sm font-medium">{{ share.path }}</dd>
                </div>
            </dl>

            <section
                v-if="phpSettingsApplicable"
                class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm"
            >
                <h2 class="text-base font-semibold">{{ t('domains.show.php_heading') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ t('domains.show.php_help') }}
                    <strong class="font-medium text-zinc-700">{{ t('domains.show.php_deploy_note') }}</strong>
                    {{ t('domains.show.php_deploy_note_rest') }}
                </p>
                <p v-if="page.props.flash?.success && currentTab === 'generale'" class="mt-3 text-sm text-emerald-700">
                    {{ page.props.flash.success }}
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
                            class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
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
                v-if="siteHosting.available"
                class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">{{ t('domains.show.site_env') }}</h2>
                        <p class="mt-1 text-sm text-zinc-500">
                            {{ t('domains.show.site_env_help') }}
                        </p>
                    </div>
                    <button
                        v-if="canMutateHosting"
                        type="button"
                        class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
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
                v-if="canManageAccess"
                class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm"
            >
                <h2 class="text-base font-semibold">{{ t('domains.show.access') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ t('domains.show.access_help') }}
                </p>
                <ul v-if="domainMembers.length" class="mt-4 divide-y divide-zinc-100 rounded-lg border border-zinc-100">
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
                            class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                            :disabled="accessForm.processing"
                        >
                            {{ t('common.invite') }}
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <div v-show="currentTab === 'database'" class="min-w-0 space-y-6">
            <section
                v-if="canMutateHosting && !domain.database_accounts?.length"
                class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm"
            >
                <h2 class="text-base font-semibold">{{ t('domains.show.create_database') }}</h2>
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
                            class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
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

            <section v-else class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">{{ t('common.database') }}</h2>
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

            <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">{{ t('domains.show.db_users') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ t('domains.show.db_users_help') }}
                </p>
                <ul v-if="domain.database_accounts?.length" class="mt-4 space-y-2">
                    <li
                        v-for="account in domain.database_accounts"
                        :key="`db-user-${account.id}`"
                        class="rounded-lg bg-zinc-50 px-3 py-2 text-sm"
                    >
                        <span class="font-medium">{{ account.username }}</span>
                        <span class="text-zinc-500">
                            · {{ account.database_name }} · {{ privilegeLabel(account.privilege) }}
                        </span>
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
        </div>

        <div v-show="currentTab === 'sftp'" class="min-w-0 space-y-6">
            <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">{{ t('common.sftp') }}</h2>
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
                        class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="sftpUserForm.processing"
                    >
                        {{ t('domains.show.create_sftp') }}
                    </button>
                </form>
            </section>
        </div>

        <div v-show="currentTab === 'laravel'" class="min-w-0 space-y-6">
            <LaravelToolkitPanel
                :domain="domain"
                :deploy-preset="laravelDeployPreset"
                :attach-processing="laravelForm.processing"
                :attach-error="laravelForm.errors.attach_laravel || ''"
                :can-open-dokploy="canOpenDokploy"
                :can-mutate-hosting="canMutateHosting"
                @attach="installLaravel"
            />

            <section
                v-if="canMutateHosting"
                class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm"
            >
                <h2 class="text-base font-semibold">{{ t('domains.show.boot_env') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ t('domains.show.boot_env_help') }}
                </p>
                <p v-if="page.props.flash?.success" class="mt-3 text-sm text-emerald-700">
                    {{ page.props.flash.success }}
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

        <div v-show="currentTab === 'sito'" class="min-w-0 space-y-6">
            <StackToolkitPanel
                :domain="domain"
                :stack-presets="stackPresets"
                :attach-processing="laravelForm.processing"
                :attach-error="laravelForm.errors.attach_laravel || ''"
                :can-open-dokploy="canOpenDokploy"
                :can-mutate-hosting="canMutateHosting"
                @attach="installLaravel"
            />
        </div>

        <section
            v-show="currentTab === 'pericolo'"
            class="min-w-0 rounded-xl border border-red-200 bg-white p-6 shadow-sm"
        >
            <h2 class="text-base font-semibold text-red-800">{{ t('domains.show.delete_heading') }}</h2>
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
    </AppLayout>
</template>
