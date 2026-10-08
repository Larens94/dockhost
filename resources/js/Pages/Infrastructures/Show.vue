<!-- Show.vue — Show component.

  exports: defineProps
  used_by: none
  rules:   none
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
-->

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import ServicePicker from '../../Components/ServicePicker.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    infrastructure: {
        type: Object,
        required: true,
    },
    services: {
        type: Array,
        required: true,
    },
    phpmyadmin_suggested_host: {
        type: String,
        default: '',
    },
    pgadmin_suggested_host: {
        type: String,
        default: '',
    },
    minio_suggested_host: {
        type: String,
        default: '',
    },
    credentials: {
        type: Object,
        default: () => ({}),
    },
});

const stackCredentialRows = computed(() => {
    const fromProp = props.credentials || {};
    const infra = props.infrastructure || {};

    return [
        {
            label: 'phpMyAdmin / MariaDB app',
            username: fromProp.mysql?.username || infra.mysql_admin_user,
            password: fromProp.mysql?.password || infra.mysql_admin_password,
            host: fromProp.mysql?.host || infra.mysql_host,
            port: fromProp.mysql?.port || infra.mysql_port,
        },
        {
            label: 'MariaDB root',
            username: fromProp.mysql_root?.username || 'root',
            password: fromProp.mysql_root?.password || infra.mysql_root_password,
            host: fromProp.mysql_root?.host || infra.mysql_host,
            port: fromProp.mysql_root?.port || infra.mysql_port,
        },
        {
            label: 'pgAdmin / Postgres',
            username: fromProp.postgres?.username || infra.postgres_admin_user,
            password: fromProp.postgres?.password || infra.postgres_admin_password,
            host: fromProp.postgres?.host || infra.postgres_host,
            port: fromProp.postgres?.port || infra.postgres_port,
        },
        {
            label: 'SFTP bootstrap',
            username: fromProp.sftp?.username || 'infra',
            password: fromProp.sftp?.password || infra.sftp_bootstrap_password,
            host: fromProp.sftp?.host || infra.sftp_public_host,
            port: fromProp.sftp?.port || infra.sftp_host_port,
        },
        fromProp.pgadmin || (infra.pgadmin_email
            ? {
                label: 'pgAdmin login',
                username: infra.pgadmin_email,
                password: infra.pgadmin_password,
                host: infra.pgadmin_domain,
            }
            : null),
        fromProp.minio || (infra.minio_root_user
            ? {
                label: 'MinIO console',
                username: infra.minio_root_user,
                password: infra.minio_root_password,
                host: infra.minio_domain,
            }
            : null),
    ].filter((row) => row && (row.username || row.password));
});

const tabs = [
    { id: 'generale', label: 'Generale' },
    { id: 'servizi', label: 'Servizi' },
    { id: 'domini', label: 'Domini' },
    { id: 'accessi', label: 'Accessi' },
    { id: 'volumi', label: 'Volumi' },
    { id: 'dokploy', label: 'Dokploy' },
    { id: 'pericolo', label: 'Pericolo' },
];

const page = usePage();
const allowedTabs = tabs.map((tab) => tab.id);

const currentTab = computed(() => {
    const query = page.url.includes('?') ? page.url.slice(page.url.indexOf('?') + 1) : '';
    const tab = new URLSearchParams(query).get('tab');

    return allowedTabs.includes(tab) ? tab : 'generale';
});

const tabHref = (tab) => {
    const base = `/infrastructures/${props.infrastructure.id}`;

    return tab === 'generale' ? base : `${base}?tab=${tab}`;
};

const tabClass = (tab) =>
    currentTab.value === tab
        ? 'border-zinc-900 text-zinc-900'
        : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-800';

const form = useForm({
    enabled_services: props.services.filter((service) => service.enabled).map((service) => service.key),
});

const phpmyadminForm = useForm({});
const pgadminForm = useForm({});
const minioForm = useForm({});
const databaseUserForm = useForm({
    database_account_id: props.infrastructure.database_accounts?.[0]?.id || '',
    privilege: 'all',
});
const sftpUserForm = useForm({
    domain_id: props.infrastructure.domains?.[0]?.id || '',
});
const deleteForm = useForm({
    slug: '',
});
const resetMysqlForm = useForm({
    slug: '',
});
const revealed = computed(() => {
    const credential = page.props.revealedCredential;

    if (!credential || credential.infrastructure_id !== props.infrastructure.id) {
        return null;
    }

    return credential;
});
const uniqueDatabases = computed(() => {
    const seen = new Set();

    return (props.infrastructure.database_accounts || []).filter((account) => {
        if (seen.has(account.database_name)) {
            return false;
        }

        seen.add(account.database_name);

        return true;
    });
});
const privilegeLabel = (privilege) => (privilege === 'select' ? 'Solo lettura (SELECT)' : 'Completi (ALL)');
const showDeleteModal = ref(false);
const showResetMysqlModal = ref(false);
const hasDomains = computed(() => (props.infrastructure.domains_count ?? 0) > 0);
const slugMatches = computed(() => deleteForm.slug === props.infrastructure.slug);
const resetMysqlSlugMatches = computed(() => resetMysqlForm.slug === props.infrastructure.slug);

const openDeleteModal = () => {
    if (hasDomains.value) {
        return;
    }

    deleteForm.clearErrors();
    deleteForm.reset();
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    if (deleteForm.processing) {
        return;
    }

    showDeleteModal.value = false;
    deleteForm.reset();
};

const destroyInfrastructure = () => {
    if (!slugMatches.value || hasDomains.value) {
        return;
    }

    deleteForm.delete(`/infrastructures/${props.infrastructure.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
        },
    });
};

const openResetMysqlModal = () => {
    resetMysqlForm.clearErrors();
    resetMysqlForm.reset();
    showResetMysqlModal.value = true;
};

const closeResetMysqlModal = () => {
    if (resetMysqlForm.processing) {
        return;
    }

    showResetMysqlModal.value = false;
    resetMysqlForm.reset();
};

const resetMysqlDatadir = () => {
    if (!resetMysqlSlugMatches.value) {
        return;
    }

    resetMysqlForm.post(actionUrl(`/infrastructures/${props.infrastructure.id}/reset-mysql`), {
        preserveScroll: true,
        onSuccess: () => {
            showResetMysqlModal.value = false;
        },
    });
};

const letsEncryptStatus = 'HTTPS Let’s Encrypt (richiede wildcard DNS già ok)';

const phpmyadminEnabled = props.services.some((service) => service.key === 'phpmyadmin' && service.enabled);
const pgadminEnabled = props.services.some((service) => service.key === 'pgadmin' && service.enabled);
const minioEnabled = props.services.some((service) => service.key === 'minio' && service.enabled);
const redisEnabled = props.services.some((service) => service.key === 'redis' && service.enabled);

const actionUrl = (path) => {
    const tab = currentTab.value;

    return tab === 'generale' ? path : `${path}?tab=${tab}`;
};

const attachPhpmyadmin = () => {
    phpmyadminForm.post(actionUrl(`/infrastructures/${props.infrastructure.id}/phpmyadmin-domain`));
};

const attachPgadmin = () => {
    pgadminForm.post(actionUrl(`/infrastructures/${props.infrastructure.id}/pgadmin-domain`));
};

const attachMinio = () => {
    minioForm.post(actionUrl(`/infrastructures/${props.infrastructure.id}/minio-domain`));
};

const createDatabaseUser = () => {
    databaseUserForm.post(actionUrl(`/infrastructures/${props.infrastructure.id}/database-users`), {
        preserveScroll: true,
    });
};

const createSftpUser = () => {
    sftpUserForm.post(actionUrl(`/infrastructures/${props.infrastructure.id}/sftp-users`), { preserveScroll: true });
};

const toggleOptional = (key) => {
    const service = props.services.find((item) => item.key === key);
    const isOn = form.enabled_services.includes(key);

    if (isOn) {
        const label = service?.label || key;
        if (
            !window.confirm(
                `Disattivare ${label}? Il compose viene ridistribuito. I volumi Docker (${props.infrastructure.slug}_${key}) possono restare sul server.`,
            )
        ) {
            return;
        }

        form.enabled_services = form.enabled_services.filter((item) => item !== key);
    } else {
        form.enabled_services = [...form.enabled_services, key];
    }

    submit();
};

const submit = () => {
    form.put(actionUrl(`/infrastructures/${props.infrastructure.id}`), { preserveScroll: true });
};

const deploySnapshot = ref(null);
const verifyingStatus = ref(false);
const verifyError = ref('');
const inspectSnapshot = ref(null);
const inspecting = ref(false);
const inspectError = ref('');

const displayedStatus = computed(() => deploySnapshot.value?.status || props.infrastructure.status);
const displayedError = computed(
    () => deploySnapshot.value?.last_error ?? props.infrastructure.last_error,
);

const statusLabel = (status) => {
    const labels = {
        deployed: 'Attivo',
        ready: 'Attivo',
        deploying: 'In distribuzione',
        degraded: 'Degradato',
        failed: 'Fallito',
        pending: 'In attesa',
    };

    return labels[status] || status;
};

const containerStateLabel = (state) => {
    const labels = {
        running: 'in esecuzione',
        healthy: 'in esecuzione',
        restarting: 'in riavvio',
        restart: 'in riavvio',
        exited: 'uscito',
        dead: 'uscito',
        missing: 'assente',
        pending: 'in coda',
        done: 'completato',
        error: 'errore',
        idle: 'inattivo',
    };

    return labels[state] || state;
};

const findingClass = (severity) => {
    if (severity === 'error') {
        return 'border-red-200 bg-red-50 text-red-800';
    }

    if (severity === 'ok') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    }

    return 'border-amber-200 bg-amber-50 text-amber-800';
};

const statusClass = computed(() => {
    const status = displayedStatus.value;

    if (status === 'ready' || status === 'deployed') {
        return 'bg-emerald-50 text-emerald-800';
    }

    if (status === 'failed') {
        return 'bg-red-50 text-red-800';
    }

    if (status === 'degraded') {
        return 'bg-amber-50 text-amber-800';
    }

    if (status === 'deploying') {
        return 'bg-sky-50 text-sky-800';
    }

    return 'bg-zinc-100 text-zinc-700';
});

const loadDeployStatus = async () => {
    verifyingStatus.value = true;
    verifyError.value = '';

    try {
        const response = await fetch(`/infrastructures/${props.infrastructure.id}/deploy-status`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json();

        if (!response.ok) {
            verifyError.value = payload.last_error || payload.message || 'Impossibile leggere lo stato da Dokploy.';
            deploySnapshot.value = payload;

            return;
        }

        deploySnapshot.value = payload;
    } catch {
        verifyError.value = 'Impossibile leggere lo stato da Dokploy.';
    } finally {
        verifyingStatus.value = false;
    }
};

const loadInspect = async () => {
    inspecting.value = true;
    inspectError.value = '';

    try {
        const response = await fetch(`/infrastructures/${props.infrastructure.id}/dokploy-inspect`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json();

        if (!response.ok) {
            inspectError.value = payload.message || 'Impossibile leggere i log da Dokploy.';
            inspectSnapshot.value = payload;

            return;
        }

        inspectSnapshot.value = payload;
    } catch {
        inspectError.value = 'Impossibile leggere i log da Dokploy.';
    } finally {
        inspecting.value = false;
    }
};

onMounted(() => {
    loadDeployStatus();

    if (currentTab.value === 'dokploy') {
        loadInspect();
    }
});

watch(currentTab, (tab) => {
    if (tab === 'dokploy' && !inspectSnapshot.value && !inspecting.value) {
        loadInspect();
    }
});

const sftpPublicHost = computed(() => {
    const host = props.infrastructure.sftp_public_host || props.infrastructure.sftp_host;
    const port = props.infrastructure.sftp_host_port ? `:${props.infrastructure.sftp_host_port}` : '';

    return `${host}${port}`;
});
</script>

<template>
    <AppLayout
        :title="infrastructure.slug"
        description="Un progetto Dokploy e uno stack Compose. Usa le schede per servizi, domini, accessi e volumi."
    >
        <template #actions>
            <div class="flex max-w-full flex-wrap items-center justify-end gap-2">
                <a
                    v-if="infrastructure.dokploy_project_url"
                    :href="infrastructure.dokploy_project_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex max-w-full items-center justify-center whitespace-normal rounded-lg border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium break-words [overflow-wrap:anywhere] hover:bg-zinc-50"
                >
                    Apri in Dokploy
                </a>
                <a
                    v-if="infrastructure.dokploy_compose_url"
                    :href="infrastructure.dokploy_compose_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex max-w-full items-center justify-center whitespace-normal rounded-lg border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium break-words [overflow-wrap:anywhere] hover:bg-zinc-50"
                >
                    Compose / Deploy
                </a>
                <Link
                    href="/infrastructures"
                    class="inline-flex max-w-full items-center justify-center whitespace-normal rounded-lg border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium break-words [overflow-wrap:anywhere] hover:bg-zinc-50"
                >
                    Crea altra
                </Link>
            </div>
        </template>

        <nav class="-mx-1 mb-6 flex min-w-0 flex-wrap gap-1 border-b border-neutral-200" aria-label="Sezioni">
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
            <p
                v-if="displayedError"
                class="min-w-0 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm break-words text-red-800 [overflow-wrap:anywhere]"
            >
                {{ displayedError }}
            </p>
            <p
                v-if="verifyError && verifyError !== displayedError"
                class="min-w-0 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm break-words text-amber-800 [overflow-wrap:anywhere]"
            >
                {{ verifyError }}
            </p>

            <div
                v-if="infrastructure.dokploy_project_url || infrastructure.dokploy_compose_url"
                class="flex min-w-0 flex-wrap gap-2"
            >
                <a
                    v-if="infrastructure.dokploy_project_url"
                    :href="infrastructure.dokploy_project_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex max-w-full items-center justify-center whitespace-normal rounded-lg border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium break-words [overflow-wrap:anywhere] hover:bg-zinc-50"
                >
                    Apri in Dokploy
                </a>
                <a
                    v-if="infrastructure.dokploy_compose_url"
                    :href="infrastructure.dokploy_compose_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex max-w-full items-center justify-center whitespace-normal rounded-lg border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium break-words [overflow-wrap:anywhere] hover:bg-zinc-50"
                >
                    Compose / Deploy
                </a>
            </div>

            <section class="min-w-0 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold">Stato Dokploy</h2>
                        <p class="mt-1 text-sm break-words text-zinc-500 [overflow-wrap:anywhere]">
                            Confronta Deployments e Containers del compose, non lo stato salvato in locale.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium hover:bg-zinc-50 disabled:opacity-50"
                        :disabled="verifyingStatus"
                        @click="loadDeployStatus"
                    >
                        {{ verifyingStatus ? 'Verifica…' : 'Verifica stato' }}
                    </button>
                </div>
                <div class="mt-4 min-w-0 rounded-lg bg-zinc-50 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Ultimo deploy</p>
                    <p
                        v-if="deploySnapshot?.last_deployment"
                        class="mt-1 text-sm break-words [overflow-wrap:anywhere]"
                    >
                        {{ containerStateLabel(deploySnapshot.last_deployment.status) }}
                        <span v-if="deploySnapshot.last_deployment.title">
                            · {{ deploySnapshot.last_deployment.title }}
                        </span>
                        <span v-if="deploySnapshot.last_deployment.created_at" class="text-zinc-500">
                            · {{ deploySnapshot.last_deployment.created_at }}
                        </span>
                    </p>
                    <p v-else class="mt-1 text-sm text-zinc-500">
                        {{ verifyingStatus ? 'Lettura da Dokploy…' : 'Nessun deployment restituito.' }}
                    </p>
                    <p
                        v-if="deploySnapshot?.compose_status"
                        class="mt-1 text-xs break-words text-zinc-500 [overflow-wrap:anywhere]"
                    >
                        Compose: {{ containerStateLabel(deploySnapshot.compose_status) }}
                    </p>
                </div>
                <ul v-if="deploySnapshot?.containers?.length" class="mt-4 grid min-w-0 gap-2 sm:grid-cols-2">
                    <li
                        v-for="container in deploySnapshot.containers"
                        :key="container.name"
                        class="min-w-0 rounded-lg border border-neutral-200 px-3 py-2 text-sm"
                    >
                        <p class="break-all font-medium [overflow-wrap:anywhere]">
                            {{ container.service || container.name }}
                        </p>
                        <p class="mt-0.5 text-xs break-words text-zinc-500 [overflow-wrap:anywhere]">
                            {{ containerStateLabel(container.state)
                            }}<span v-if="container.status"> · {{ container.status }}</span>
                        </p>
                    </li>
                </ul>
                <p v-else-if="!verifyingStatus" class="mt-4 text-sm break-words text-zinc-500 [overflow-wrap:anywhere]">
                    Nessun container restituito da Dokploy.
                </p>
            </section>

            <div class="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="min-w-0 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Stato</p>
                    <p class="mt-2">
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-medium" :class="statusClass">
                            {{ statusLabel(displayedStatus) }}
                        </span>
                    </p>
                </div>
                <div class="min-w-0 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Slug</p>
                    <p class="mt-2 truncate text-sm font-medium">{{ infrastructure.slug }}</p>
                </div>
                <div class="min-w-0 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Progetto</p>
                    <p class="mt-2 break-all text-sm font-medium">{{ infrastructure.dokploy_project_id || '—' }}</p>
                </div>
                <div class="min-w-0 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Environment</p>
                    <p class="mt-2 break-all text-sm font-medium">{{ infrastructure.dokploy_environment_id || '—' }}</p>
                </div>
                <div class="min-w-0 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Compose</p>
                    <p class="mt-2 break-all text-sm font-medium">{{ infrastructure.dokploy_compose_id || '—' }}</p>
                </div>
                <div class="min-w-0 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm sm:col-span-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">SFTP pubblico</p>
                    <p class="mt-2 break-all text-sm font-medium">{{ sftpPublicHost }}</p>
                    <p class="mt-1 text-xs break-words text-zinc-500">interno {{ infrastructure.sftp_host }}</p>
                </div>
            </div>

            <section class="min-w-0 rounded-xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
                <h2 class="text-base font-semibold text-amber-950">Endpoint e password</h2>
                <p class="mt-1 mb-5 text-sm break-words text-amber-900 [overflow-wrap:anywhere]">
                    In chiaro solo in sviluppo. In phpMyAdmin usa
                    <span class="font-mono">infra</span>, non root: root dalla GUI spesso è rifiutato
                    (connessione da un altro container).
                </p>
                <ul class="mb-5 space-y-2 text-sm text-amber-950">
                    <li
                        v-for="row in stackCredentialRows"
                        :key="row.label"
                        class="rounded-lg border border-amber-200 bg-white px-3 py-2"
                    >
                        <p class="text-xs uppercase tracking-wide text-amber-800">{{ row.label }}</p>
                        <p class="mt-1 break-all font-mono">{{ row.username || '—' }} / {{ row.password || '—' }}</p>
                        <p v-if="row.host" class="mt-0.5 text-xs text-amber-800">
                            {{ row.host }}{{ row.port ? ':' + row.port : '' }}
                        </p>
                    </li>
                </ul>
                <dl class="grid min-w-0 gap-3 text-sm sm:grid-cols-2">
                    <div class="min-w-0 rounded-lg bg-white px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">MariaDB</dt>
                        <dd class="mt-0.5 break-all font-medium">
                            {{ infrastructure.mysql_host }}:{{ infrastructure.mysql_port }} ·
                            {{ infrastructure.mysql_admin_user }}
                        </dd>
                    </div>
                    <div class="min-w-0 rounded-lg bg-white px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">Postgres</dt>
                        <dd class="mt-0.5 break-all font-medium">
                            {{ infrastructure.postgres_host }}:{{ infrastructure.postgres_port }} ·
                            {{ infrastructure.postgres_admin_user }}
                        </dd>
                    </div>
                    <div class="min-w-0 rounded-lg bg-white px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">SFTP</dt>
                        <dd class="mt-0.5 break-all font-medium">{{ sftpPublicHost }}</dd>
                    </div>
                    <div class="min-w-0 rounded-lg bg-zinc-50 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">Storage</dt>
                        <dd class="mt-0.5 break-all font-medium">{{ infrastructure.storage_root }}</dd>
                    </div>
                    <div v-if="infrastructure.redis_host || redisEnabled" class="min-w-0 rounded-lg bg-zinc-50 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">Redis</dt>
                        <dd class="mt-0.5 break-all font-medium">
                            {{ infrastructure.redis_host || infrastructure.slug + '-redis' }}:6379
                        </dd>
                    </div>
                    <div v-if="infrastructure.minio_host || minioEnabled" class="min-w-0 rounded-lg bg-zinc-50 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">MinIO API</dt>
                        <dd class="mt-0.5 break-all font-medium">
                            {{ infrastructure.minio_host || infrastructure.slug + '-minio' }}:9000
                        </dd>
                    </div>
                </dl>
            </section>
        </div>

        <section v-show="currentTab === 'dokploy'" class="min-w-0 space-y-4">
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">Log Dokploy</h2>
                        <p class="mt-1 text-sm break-words text-zinc-500 [overflow-wrap:anywhere]">
                            Stato compose, container e log live (mysql-grants, MariaDB, phpMyAdmin) via API Dokploy.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium hover:bg-zinc-50 disabled:opacity-50"
                        :disabled="inspecting"
                        @click="loadInspect"
                    >
                        {{ inspecting ? 'Lettura…' : 'Aggiorna log' }}
                    </button>
                </div>
                <p
                    v-if="inspectError"
                    class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
                >
                    {{ inspectError }}
                </p>
                <ul v-if="inspectSnapshot?.findings?.length" class="mt-4 space-y-2">
                    <li
                        v-for="finding in inspectSnapshot.findings"
                        :key="finding.code + finding.message"
                        class="rounded-lg border px-3 py-2 text-sm"
                        :class="findingClass(finding.severity)"
                    >
                        <span class="font-medium">{{ finding.code }}</span>
                        <span v-if="finding.service" class="text-xs"> · {{ finding.service }}</span>
                        <p class="mt-1 break-words [overflow-wrap:anywhere]">{{ finding.message }}</p>
                    </li>
                </ul>
            </div>
            <section
                v-for="log in inspectSnapshot?.logs || []"
                :key="log.id || log.name"
                class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm"
            >
                <h3 class="text-sm font-semibold break-all">
                    {{ log.service || log.name }}
                    <span class="font-normal text-zinc-500">
                        · {{ containerStateLabel(log.state) }}
                        <span v-if="log.status"> · {{ log.status }}</span>
                    </span>
                </h3>
                <p v-if="log.error" class="mt-2 text-sm text-red-700">{{ log.error }}</p>
                <pre
                    class="mt-3 max-h-80 overflow-auto rounded-lg bg-zinc-950 p-3 text-xs whitespace-pre-wrap text-zinc-100"
                    >{{ log.body || '(log vuoti)' }}</pre
                >
            </section>
            <p
                v-if="!inspecting && inspectSnapshot && !(inspectSnapshot.logs || []).length"
                class="text-sm text-zinc-500"
            >
                Nessun log restituito per i servizi monitorati.
            </p>
        </section>

        <section v-show="currentTab === 'servizi'" class="min-w-0 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold">Modifica servizi</h2>
            <p class="mt-1 mb-5 text-sm break-words text-zinc-500 [overflow-wrap:anywhere]">
                Clicca una card per aggiungere un servizio: compose, deploy e domini pubblici (phpMyAdmin, pgAdmin,
                MinIO) partono subito. Togliere un servizio chiede conferma. I volumi restano sul server.
            </p>
            <form class="space-y-4" @submit.prevent="submit">
                <ServicePicker
                    :services="services"
                    :selected="form.enabled_services"
                    :disabled="form.processing || !infrastructure.dokploy_compose_id"
                    @toggle="toggleOptional"
                />
                <p v-if="form.errors.enabled_services" class="text-sm break-words text-red-600 [overflow-wrap:anywhere]">
                    {{ form.errors.enabled_services }}
                </p>
                <button
                    type="submit"
                    class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                    :disabled="form.processing || !infrastructure.dokploy_compose_id"
                >
                    Aggiorna stack
                </button>
            </form>
        </section>

        <div v-show="currentTab === 'domini'" class="min-w-0 space-y-6">
            <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">phpMyAdmin</h2>
                <p class="mt-1 mb-5 text-sm break-words text-zinc-500 [overflow-wrap:anywhere]">
                    Serve un record DNS wildcard una tantum
                    (<span class="font-medium text-zinc-700"
                        >*.{{
                            phpmyadmin_suggested_host.split('.').slice(1).join('.') || 'cloud.silicoreautomation.com'
                        }}</span
                    >); non usare l'URL della dashboard Dokploy. Il pannello crea da solo
                    <span class="font-medium break-all text-zinc-700">{{ phpmyadmin_suggested_host }}</span>
                    sul servizio phpmyadmin, porta 80, HTTPS Let’s Encrypt. Dopo il dominio il compose
                    viene ridistribuito: senza quello Traefik risponde 404 e il certificato non parte.
                </p>
                <div v-if="infrastructure.phpmyadmin_domain" class="space-y-1">
                    <p class="text-sm break-all">
                        <a
                            :href="`https://${infrastructure.phpmyadmin_domain}`"
                            class="font-medium text-zinc-900 underline underline-offset-2"
                            target="_blank"
                            rel="noreferrer"
                        >
                            https://{{ infrastructure.phpmyadmin_domain }}
                        </a>
                    </p>
                    <p class="text-sm text-zinc-500">{{ letsEncryptStatus }}</p>
                </div>
                <form v-else-if="phpmyadminEnabled" class="space-y-3" @submit.prevent="attachPhpmyadmin">
                    <p class="text-sm break-words text-zinc-600">
                        Hostname previsto:
                        <span class="font-medium break-all text-zinc-900">{{ phpmyadmin_suggested_host }}</span>
                    </p>
                    <p v-if="phpmyadminForm.errors.phpmyadmin" class="text-sm break-words text-red-600 [overflow-wrap:anywhere]">
                        {{ phpmyadminForm.errors.phpmyadmin }}
                    </p>
                    <button
                        type="submit"
                        class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="phpmyadminForm.processing || !infrastructure.dokploy_compose_id"
                    >
                        Collega phpMyAdmin
                    </button>
                </form>
                <p v-else class="text-sm break-words text-zinc-500">
                    Attiva phpMyAdmin e aggiorna lo stack per collegare il dominio.
                </p>
            </section>

            <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">pgAdmin</h2>
                <p class="mt-1 mb-5 text-sm break-words text-zinc-500 [overflow-wrap:anywhere]">
                    GUI Postgres. Hostname
                    <span class="font-medium break-all text-zinc-700">{{ pgadmin_suggested_host }}</span>
                    sul servizio pgadmin, porta 80, HTTPS Let’s Encrypt. Login:
                    <span class="font-medium text-zinc-700">{{
                        infrastructure.pgadmin_email || 'generato allo stack'
                    }}</span>.
                </p>
                <div v-if="infrastructure.pgadmin_domain" class="space-y-1">
                    <p class="text-sm break-all">
                        <a
                            :href="`https://${infrastructure.pgadmin_domain}`"
                            class="font-medium text-zinc-900 underline underline-offset-2"
                            target="_blank"
                            rel="noreferrer"
                        >
                            https://{{ infrastructure.pgadmin_domain }}
                        </a>
                    </p>
                    <p class="text-sm text-zinc-500">{{ letsEncryptStatus }}</p>
                </div>
                <form v-else-if="pgadminEnabled" class="space-y-3" @submit.prevent="attachPgadmin">
                    <p class="text-sm break-words text-zinc-600">
                        Hostname previsto:
                        <span class="font-medium break-all text-zinc-900">{{ pgadmin_suggested_host }}</span>
                    </p>
                    <p v-if="pgadminForm.errors.pgadmin" class="text-sm break-words text-red-600 [overflow-wrap:anywhere]">
                        {{ pgadminForm.errors.pgadmin }}
                    </p>
                    <button
                        type="submit"
                        class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="pgadminForm.processing || !infrastructure.dokploy_compose_id"
                    >
                        Collega pgAdmin
                    </button>
                </form>
                <p v-else class="text-sm break-words text-zinc-500">
                    Attiva pgAdmin e aggiorna lo stack per collegare il dominio.
                </p>
            </section>

            <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">MinIO</h2>
                <p class="mt-1 mb-5 text-sm break-words text-zinc-500 [overflow-wrap:anywhere]">
                    Console pubblica
                    <span class="font-medium break-all text-zinc-700">{{ minio_suggested_host }}</span>
                    (console 9001, HTTPS Let’s Encrypt via HTTP-01 su :80). API interna
                    <span class="font-medium text-zinc-700">{{ infrastructure.slug }}-minio:9000</span>.
                </p>
                <div v-if="infrastructure.minio_domain" class="space-y-1">
                    <p class="text-sm break-all">
                        <a
                            :href="`https://${infrastructure.minio_domain}`"
                            class="font-medium text-zinc-900 underline underline-offset-2"
                            target="_blank"
                            rel="noreferrer"
                        >
                            https://{{ infrastructure.minio_domain }}
                        </a>
                    </p>
                    <p class="text-sm text-zinc-500">{{ letsEncryptStatus }}</p>
                </div>
                <form v-else-if="minioEnabled" class="space-y-3" @submit.prevent="attachMinio">
                    <p class="text-sm break-words text-zinc-600">
                        Hostname previsto:
                        <span class="font-medium break-all text-zinc-900">{{ minio_suggested_host }}</span>
                    </p>
                    <p v-if="minioForm.errors.minio" class="text-sm break-words text-red-600 [overflow-wrap:anywhere]">
                        {{ minioForm.errors.minio }}
                    </p>
                    <button
                        type="submit"
                        class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="minioForm.processing || !infrastructure.dokploy_compose_id"
                    >
                        Collega console MinIO
                    </button>
                </form>
                <p v-else class="text-sm break-words text-zinc-500">
                    Attiva MinIO e aggiorna lo stack per collegare il dominio.
                </p>
            </section>
        </div>

        <div v-show="currentTab === 'accessi'" class="min-w-0 space-y-6">
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
                <h2 class="text-base font-semibold text-amber-950">Credenziali stack (sviluppo)</h2>
                <p class="mt-1 mb-4 text-sm text-amber-900">
                    In phpMyAdmin usa
                    <span class="font-mono">infra</span>
                    (prima riga), non root. Root dalla GUI arriva da un altro IP e MariaDB lo rifiuta.
                </p>
                <ul class="space-y-2 text-sm text-amber-950">
                    <li
                        v-for="row in stackCredentialRows"
                        :key="row.label"
                        class="rounded-lg border border-amber-200 bg-white px-3 py-2"
                    >
                        <p class="text-xs uppercase tracking-wide text-amber-800">{{ row.label }}</p>
                        <p class="mt-1 break-all font-mono">
                            {{ row.username }} / {{ row.password }}
                        </p>
                        <p v-if="row.host" class="mt-0.5 text-xs text-amber-800">
                            {{ row.host }}{{ row.port ? ':' + row.port : '' }}
                        </p>
                    </li>
                </ul>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">Utenti database</h2>
                <p class="mt-1 mb-5 text-sm break-words text-zinc-500 [overflow-wrap:anywhere]">
                    Utenti extra su un database già presente su questo MariaDB/Postgres condiviso. Privilegi: completi
                    (ALL) o sola lettura (SELECT). Non si crea un container nuovo.
                </p>
                <ul v-if="infrastructure.database_accounts?.length" class="mb-4 space-y-2">
                    <li
                        v-for="account in infrastructure.database_accounts"
                        :key="account.id"
                        class="rounded-lg bg-zinc-50 px-3 py-2 text-sm break-words"
                    >
                        <span class="font-medium">{{ account.username }}</span>
                        <span class="text-zinc-500">
                            · {{ account.database_name }} · {{ privilegeLabel(account.privilege) }}
                        </span>
                        <span v-if="account.password" class="mt-1 block font-mono text-xs">{{ account.password }}</span>
                    </li>
                </ul>
                <p v-else class="mb-4 text-sm text-zinc-500">
                    Nessun database di sito su questa infra. Le select restano vuote finché non crei un cliente,
                    uno spazio e un dominio con «Crea database». L’utente stack
                    <span class="font-mono">infra</span>
                    è nel riquadro credenziali sopra (phpMyAdmin).
                </p>
                <p v-if="!uniqueDatabases.length" class="mb-4">
                    <Link href="/customers" class="text-sm font-medium text-zinc-900 underline underline-offset-2">
                        Vai a Clienti
                    </Link>
                </p>
                <form v-if="uniqueDatabases.length" class="grid gap-3 sm:grid-cols-2" @submit.prevent="createDatabaseUser">
                    <div>
                        <label class="block text-xs font-medium" for="infra-db-account">Database</label>
                        <select
                            id="infra-db-account"
                            v-model="databaseUserForm.database_account_id"
                            required
                            class="mt-1.5 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                        >
                            <option v-for="account in uniqueDatabases" :key="account.id" :value="account.id">
                                {{ account.database_name }} ({{ account.engine }})
                            </option>
                        </select>
                        <p v-if="databaseUserForm.errors.database_account_id" class="mt-1 text-sm text-red-600">
                            {{ databaseUserForm.errors.database_account_id }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium" for="infra-db-priv">Privilegi</label>
                        <select
                            id="infra-db-priv"
                            v-model="databaseUserForm.privilege"
                            required
                            class="mt-1.5 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                        >
                            <option value="all">Completi (ALL)</option>
                            <option value="select">Solo lettura (SELECT)</option>
                        </select>
                        <p v-if="databaseUserForm.errors.privilege" class="mt-1 text-sm text-red-600">
                            {{ databaseUserForm.errors.privilege }}
                        </p>
                    </div>
                    <div class="sm:col-span-2">
                        <button
                            type="submit"
                            class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                            :disabled="databaseUserForm.processing || !uniqueDatabases.length"
                        >
                            Crea utente database
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">Utenti SFTP</h2>
                <p class="mt-1 mb-5 text-sm break-words text-zinc-500 [overflow-wrap:anywhere]">
                    Utenti atmoz/sftp di questo stack. FTPS arriverà dopo. Home sotto lo storage del dominio; chroot è
                    implicito.
                </p>
                <ul v-if="infrastructure.sftp_users?.length" class="mb-4 space-y-2">
                    <li
                        v-for="user in infrastructure.sftp_users"
                        :key="user.id"
                        class="rounded-lg bg-zinc-50 px-3 py-2 text-sm break-all"
                    >
                        <span class="font-medium">{{ user.username }}</span>
                        <span class="text-zinc-500"> → {{ user.home_path }}</span>
                        <span v-if="user.password" class="mt-1 block font-mono text-xs">{{ user.password }}</span>
                    </li>
                </ul>
                <p v-if="!(infrastructure.domains || []).length" class="mb-4 text-sm text-zinc-500">
                    Nessun dominio su questa infra: la select home SFTP è vuota. Crea prima un dominio su questo stack.
                </p>
                <form
                    v-if="(infrastructure.domains || []).length"
                    class="grid gap-3 sm:grid-cols-2"
                    @submit.prevent="createSftpUser"
                >
                    <div>
                        <label class="block text-xs font-medium" for="infra-sftp-domain">Dominio (home)</label>
                        <select
                            id="infra-sftp-domain"
                            v-model="sftpUserForm.domain_id"
                            required
                            class="mt-1.5 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                        >
                            <option v-for="domain in infrastructure.domains || []" :key="domain.id" :value="domain.id">
                                {{ domain.fqdn }}
                            </option>
                        </select>
                        <p v-if="sftpUserForm.errors.domain_id" class="mt-1 text-sm text-red-600">
                            {{ sftpUserForm.errors.domain_id }}
                        </p>
                    </div>
                    <div class="flex items-end">
                        <button
                            type="submit"
                            class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                            :disabled="sftpUserForm.processing || !(infrastructure.domains || []).length"
                        >
                            Crea utente SFTP
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <section v-show="currentTab === 'volumi'" class="min-w-0 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold">Volumi di questa infrastruttura</h2>
            <p class="mt-2 text-sm break-words text-zinc-500 [overflow-wrap:anywhere]">
                Vivono nello stack Compose di {{ infrastructure.slug }}, non sull'applicazione DokHosts. Il pannello
                scrive users.conf e le cartelle via {{ infrastructure.slug }}-sftp-sync (rete storage sulle infra
                nuove).
            </p>
            <ul class="mt-4 space-y-2 text-sm text-zinc-700">
                <li class="break-all">
                    <span class="font-medium">{{ infrastructure.slug }}_data</span>
                    <span class="text-zinc-500"> — storage clienti e home SFTP (/data)</span>
                </li>
                <li class="break-all">
                    <span class="font-medium">{{ infrastructure.mariadb_volume_name || 'mariadb (default compose)' }}</span>
                    <span class="text-zinc-500"> — datadir MariaDB. «Aggiorna stack» non lo ricrea.</span>
                </li>
                <li class="break-all">
                    <span class="font-medium">postgres / sftp_config</span>
                    <span class="text-zinc-500"> — volumi dello stack Dokploy (non riusati al ricreo dello stack)</span>
                </li>
            </ul>
        </section>

        <section v-show="currentTab === 'pericolo'" class="min-w-0 rounded-xl border border-red-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-red-800">Zona pericolosa</h2>
            <p class="mt-1 mb-5 text-sm break-words text-zinc-500 [overflow-wrap:anywhere]">
                Ferma i container, elimina compose e progetto Dokploy, toglie i volumi dello stack
                e i volumi Docker non usati rimasti da deploy precedenti dello stesso slug.
                I clienti non vengono cancellati.
            </p>
            <p v-if="hasDomains" class="mb-4 text-sm break-words text-red-600">
                Ci sono domini collegati. Scollegali prima di eliminare l'infrastruttura.
            </p>
            <p v-if="deleteForm.errors.slug" class="mb-4 text-sm break-words text-red-600">{{ deleteForm.errors.slug }}</p>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100"
                    @click="openResetMysqlModal"
                >
                    Ricrea datadir MariaDB
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-red-200 bg-red-50 px-3.5 py-2 text-sm font-medium text-red-800 hover:bg-red-100 disabled:opacity-50"
                    :disabled="hasDomains"
                    @click="openDeleteModal"
                >
                    Elimina
                </button>
            </div>
        </section>

        <div
            v-if="showResetMysqlModal"
            class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/40 p-4"
            @click.self="closeResetMysqlModal"
        >
            <div class="w-full max-w-lg rounded-xl border border-neutral-200 bg-white p-6 shadow-lg">
                <h3 class="text-base font-semibold text-amber-950">Ricreare il datadir MariaDB di {{ infrastructure.slug }}?</h3>
                <p class="mt-2 text-sm break-words text-zinc-600 [overflow-wrap:anywhere]">
                    Lo stack resta. MariaDB parte su un volume nuovo così MYSQL_ROOT_HOST e le password del
                    pannello vengono applicate. I database dei siti su questo MariaDB si perdono.
                </p>
                <form class="mt-5 space-y-4" @submit.prevent="resetMysqlDatadir">
                    <div>
                        <label class="block text-sm font-medium" for="confirm-mysql-slug">
                            Per confermare, digita
                            <span class="font-semibold">{{ infrastructure.slug }}</span>
                        </label>
                        <input
                            id="confirm-mysql-slug"
                            v-model="resetMysqlForm.slug"
                            type="text"
                            autocomplete="off"
                            class="mt-1.5 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                        />
                        <p v-if="resetMysqlForm.errors.slug" class="mt-1 text-sm text-red-600">
                            {{ resetMysqlForm.errors.slug }}
                        </p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium hover:bg-zinc-50"
                            :disabled="resetMysqlForm.processing"
                            @click="closeResetMysqlModal"
                        >
                            Annulla
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-amber-800 px-3.5 py-2 text-sm font-medium text-white hover:bg-amber-900 disabled:opacity-50"
                            :disabled="resetMysqlForm.processing || !resetMysqlSlugMatches"
                        >
                            Ricrea datadir
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="showDeleteModal"
            class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/40 p-4"
            @click.self="closeDeleteModal"
        >
            <div class="w-full max-w-lg rounded-xl border border-neutral-200 bg-white p-6 shadow-lg">
                <h3 class="text-base font-semibold text-red-800">Eliminare {{ infrastructure.slug }}?</h3>
                <p class="mt-2 text-sm break-words text-zinc-600 [overflow-wrap:anywhere]">
                    Ferma i container, elimina compose, progetto e volumi Docker di questo slug. Non si può
                    annullare.
                </p>
                <form class="mt-5 space-y-4" @submit.prevent="destroyInfrastructure">
                    <div>
                        <label class="block text-sm font-medium" for="confirm-slug">
                            Per confermare, digita
                            <span class="font-semibold">{{ infrastructure.slug }}</span>
                        </label>
                        <input
                            id="confirm-slug"
                            v-model="deleteForm.slug"
                            type="text"
                            autocomplete="off"
                            class="mt-1.5 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                        />
                        <p v-if="deleteForm.errors.slug" class="mt-1 text-sm text-red-600">{{ deleteForm.errors.slug }}</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium hover:bg-zinc-50"
                            :disabled="deleteForm.processing"
                            @click="closeDeleteModal"
                        >
                            Annulla
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-red-700 px-3.5 py-2 text-sm font-medium text-white hover:bg-red-800 disabled:opacity-50"
                            :disabled="deleteForm.processing || !slugMatches"
                        >
                            Elimina
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
