<!-- LaravelToolkitPanel.vue — LaravelToolkitPanel component.

  exports: defineProps | emit:attach
  used_by: none
  rules:   none
  agent:   composer-2.5-fast | cursor | 2026-09-23 | s_runts_toolkit | Hint for Pipeline dati (RUNTS) reteinazione presets in catalog
  agent:   composer-2.5-fast | cursor | 2026-09-23 | s_git_toolkit_disc | Catalog source badge (GitLab vs fallback) from status
  agent:   composer-2.5-fast | cursor | 2026-09-23 | s_pipeline_etl | Artisan copy-only presets; hint GitLab + comandi custom.
  agent:   composer-2.5-fast | cursor | 2026-09-23 | s_toolkit_catalog | Artisan/Composer/npm presets from status command_catalog
  agent:   composer-2.5-fast | cursor | 2026-09-24 | s_gitlab_url_default | Modal URL from status gitlab_url_default / panel_gitlab
  agent:   composer-2.5-fast | cursor | 2026-09-24 | s_dokploy_gitlab_link | Usa GitLab di Dokploy + PAT opzionale nel banner Toolkit
  agent:   composer-2.5-fast | cursor | 2026-09-24 | s_toolkit_terminal | Preset copiano comando e aprono Dokploy General/Terminal quando exec API assente
-->

<script setup>
    import { computed, onMounted, ref, watch } from 'vue';
    import { useForm, usePage } from '@inertiajs/vue3';
    import { usePanelTranslations } from '../composables/usePanelTranslations';
    import LaravelLogo from './LaravelLogo.vue';

const { t } = usePanelTranslations();

const props = defineProps({
    domain: {
        type: Object,
        required: true,
    },
    attachProcessing: {
        type: Boolean,
        default: false,
    },
    attachError: {
        type: String,
        default: '',
    },
    deployPreset: {
        type: Object,
        default: () => ({}),
    },
    canOpenDokploy: {
        type: Boolean,
        default: true,
    },
    canMutateHosting: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['attach']);

const sections = computed(() => [
    { id: 'panoramica', label: t('toolkit.sections.overview') },
    { id: 'artisan', label: t('toolkit.sections.artisan') },
    { id: 'composer', label: t('toolkit.sections.composer') },
    { id: 'node', label: t('toolkit.sections.node') },
    { id: 'deploy', label: t('toolkit.sections.deploy') },
    { id: 'schedulazioni', label: t('toolkit.sections.schedules') },
    { id: 'code', label: t('toolkit.sections.queues') },
    { id: 'log', label: t('toolkit.sections.logs') },
]);

const page = usePage();
const deployForm = useForm({});

const sectionFromUrl = () => {
    const query = page.url.includes('?') ? page.url.slice(page.url.indexOf('?') + 1) : '';
    const section = new URLSearchParams(query).get('section');

    return sections.value.some((item) => item.id === section) ? section : 'panoramica';
};

const currentSection = ref(sectionFromUrl());
const overview = ref(null);
const overviewLoading = ref(false);
const overviewError = ref('');

const artisanCommand = ref('');
const composerCommand = ref('');
const npmCommand = ref('');
const artisanOutput = ref('');
const composerOutput = ref('');
const npmOutput = ref('');
const artisanError = ref('');
const composerError = ref('');
const npmError = ref('');
const running = ref('');

const emptyCatalog = () => ({ artisan: [], composer: [], npm: [] });

const commandCatalog = computed(() => overview.value?.command_catalog ?? emptyCatalog());

const catalogFromGit = computed(() => overview.value?.command_catalog_source === 'git');

const catalogHint = computed(() => overview.value?.command_catalog_message ?? '');

const dokployGitLabAvailable = computed(() =>
    Boolean(overview.value?.panel_gitlab?.dokploy_gitlab_available),
);

const showGitLabConnectBanner = computed(
    () =>
        props.canOpenDokploy
        && attached.value
        && !catalogFromGit.value
        && !overview.value?.panel_gitlab?.token_configured,
);

const GITLAB_URL_FALLBACK = 'https://git.silicoreautomation.com';

const gitlabUrlDefault = computed(
    () =>
        overview.value?.gitlab_url_default ||
        overview.value?.panel_gitlab?.default_url ||
        GITLAB_URL_FALLBACK,
);

const showGitLabModal = ref(false);
const gitlabConnectUrl = ref(GITLAB_URL_FALLBACK);
const gitlabConnectToken = ref('');
const gitlabConnectError = ref('');
const gitlabConnectSuccess = ref('');
const gitlabConnectLoading = ref(false);

const openGitLabModal = () => {
    gitlabConnectUrl.value = gitlabUrlDefault.value;
    gitlabConnectToken.value = '';
    gitlabConnectError.value = '';
    gitlabConnectSuccess.value = '';
    showGitLabModal.value = true;
};

const closeGitLabModal = () => {
    if (gitlabConnectLoading.value) {
        return;
    }

    showGitLabModal.value = false;
};

const refreshToolkitFromDokployGitLab = async () => {
    gitlabConnectLoading.value = true;
    gitlabConnectError.value = '';
    gitlabConnectSuccess.value = '';

    try {
        await loadOverview();

        if (catalogFromGit.value) {
            gitlabConnectSuccess.value = t('toolkit.catalog_read');
        } else {
            gitlabConnectError.value =
                overview.value?.command_catalog_message ||
                t('toolkit.gitlab_unusable');
        }
    } catch {
        gitlabConnectError.value = t('toolkit.network_read');
    } finally {
        gitlabConnectLoading.value = false;
    }
};

const submitGitLabCredentials = async () => {
    gitlabConnectLoading.value = true;
    gitlabConnectError.value = '';
    gitlabConnectSuccess.value = '';

    try {
        const response = await fetch('/panel/gitlab/credentials', {
            method: 'POST',
            headers: csrfHeaders(),
            body: JSON.stringify({
                gitlab_url: gitlabConnectUrl.value.trim(),
                token: gitlabConnectToken.value,
            }),
        });
        const payload = await response.json();

        if (!response.ok) {
            gitlabConnectError.value =
                payload.message ||
                payload.errors?.token?.[0] ||
                payload.errors?.gitlab_url?.[0] ||
                t('gitlab.save_failed');

            return;
        }

        gitlabConnectSuccess.value = payload.message || t('gitlab.connected');
        if (payload.redeploy_hint) {
            gitlabConnectSuccess.value += ` ${payload.redeploy_hint}`;
        }

        gitlabConnectToken.value = '';
        await loadOverview();
        window.setTimeout(() => {
            if (showGitLabModal.value) {
                closeGitLabModal();
            }
        }, 1200);
    } catch {
        gitlabConnectError.value = t('toolkit.network_connect');
    } finally {
        gitlabConnectLoading.value = false;
    }
};

const nixpacksBuildEnv = computed(() =>
    ['NIXPACKS_INSTALL_CMD', 'NIXPACKS_BUILD_CMD']
        .filter((key) => props.deployPreset?.[key])
        .map((key) => `${key}=${props.deployPreset[key]}`)
        .join('\n'),
);

const startCommandOneLiner = computed(() => props.deployPreset?.NIXPACKS_START_CMD || '');

const applyDeployConfig = () => {
    deployForm.post(`/domains/${props.domain.id}/laravel/deploy-config`, { preserveScroll: true });
};

const copied = ref('');

const copySnippet = async (id, text) => {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = id;
        window.setTimeout(() => {
            if (copied.value === id) {
                copied.value = '';
            }
        }, 2000);
    } catch {
        copied.value = '';
    }
};

const attached = computed(() => Boolean(props.domain.dokploy_application));
const dokployUrl = computed(() => props.domain.dokploy_application_url);
const ready = computed(() => Boolean(overview.value?.ready));
const canExec = computed(() => Boolean(overview.value?.ready && overview.value?.exec_available));
const terminalWorkflow = computed(() => Boolean(overview.value?.terminal_workflow));
const canRunCommands = computed(() => canExec.value || terminalWorkflow.value);
const terminalUrl = computed(
    () => overview.value?.dokploy_terminal_url || dokployUrl.value || null,
);
const recipeHint = computed(
    () =>
        overview.value?.exec_message ||
        t('toolkit.recipe_hint'),
);
const disabledHint = computed(
    () =>
        overview.value?.exec_message ||
        overview.value?.message ||
        t('toolkit.not_ready'),
);

const fullShellCommand = (kind, trimmed) => {
    if (kind === 'artisan') {
        return `php artisan ${trimmed}`;
    }

    if (kind === 'composer') {
        return `composer ${trimmed}`;
    }

    return `npm ${trimmed}`;
};

const openDokployTerminalPage = () => {
    if (!terminalUrl.value) {
        return;
    }

    window.open(terminalUrl.value, '_blank', 'noopener');
};

const copyAndOpenTerminal = async (kind, command, snippetId) => {
    const trimmed = command.trim();

    if (!trimmed) {
        return;
    }

    const full = fullShellCommand(kind, trimmed);

    clearOutput(kind);
    await copySnippet(snippetId ?? `${kind}-${trimmed}`, full);
    openDokployTerminalPage();
    setStdout(
        kind,
        `${overview.value?.exec_message || t('toolkit.copied_command')}\n\n${full}\n\n${t('toolkit.paste_hint')}`,
    );
};

const csrfHeaders = () => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    const token = match ? decodeURIComponent(match[1]) : '';

    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': token,
    };
};

const loadOverview = async () => {
    if (!attached.value) {
        overview.value = null;

        return;
    }

    overviewLoading.value = true;
    overviewError.value = '';

    try {
        const response = await fetch(`/domains/${props.domain.id}/laravel/status`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json();

        if (!response.ok) {
            overviewError.value = payload.message || t('toolkit.status_failed');

            return;
        }

        overview.value = payload;
    } catch {
        overviewError.value = t('toolkit.status_failed');
    } finally {
        overviewLoading.value = false;
    }
};

const clearOutput = (kind) => {
    if (kind === 'artisan') {
        artisanError.value = '';
        artisanOutput.value = '';
    } else if (kind === 'composer') {
        composerError.value = '';
        composerOutput.value = '';
    } else {
        npmError.value = '';
        npmOutput.value = '';
    }
};

const setError = (kind, message) => {
    if (kind === 'artisan') {
        artisanError.value = message;
    } else if (kind === 'composer') {
        composerError.value = message;
    } else {
        npmError.value = message;
    }
};

const setStdout = (kind, stdout) => {
    const text = stdout || '(nessun output)';

    if (kind === 'artisan') {
        artisanOutput.value = text;
    } else if (kind === 'composer') {
        composerOutput.value = text;
    } else {
        npmOutput.value = text;
    }
};

const runCommand = async (kind, command) => {
    const trimmed = command.trim();

    if (!trimmed) {
        return;
    }

    running.value = kind;
    clearOutput(kind);

    try {
        const response = await fetch(`/domains/${props.domain.id}/laravel/${kind}`, {
            method: 'POST',
            headers: csrfHeaders(),
            body: JSON.stringify({ command: trimmed }),
        });
        const payload = await response.json();

        if (!response.ok) {
            setError(kind, payload.errors?.command?.[0] || payload.message || 'Comando rifiutato.');

            return;
        }

        if (payload.mode === 'terminal') {
            await copySnippet(`${kind}-run`, payload.command);
            openDokployTerminalPage();
            setStdout(
                kind,
                `${payload.message || 'Comando validato.'}\n\n${payload.command}\n\nSu Dokploy: General → Open Terminal, incolla ed esegui.`,
            );

            return;
        }

        setStdout(kind, payload.stdout);
    } catch {
        setError(kind, t('toolkit.command_failed'));
    } finally {
        running.value = '';
    }
};

const copyNpmCommand = async (command) => {
    if (terminalWorkflow.value) {
        await copyAndOpenTerminal('npm', command);

        return;
    }

    await copySnippet(`npm-${command}`, fullShellCommand('npm', command));
};

const copyArtisanCommand = async (command) => {
    if (terminalWorkflow.value) {
        await copyAndOpenTerminal('artisan', command);

        return;
    }

    await copySnippet(`artisan-${command}`, fullShellCommand('artisan', command));
};

const sectionClass = (id) =>
    currentSection.value === id
        ? 'border-zinc-900 text-zinc-900'
        : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-800';

onMounted(() => {
    loadOverview();
});

watch(
    () => props.domain.dokploy_application?.id,
    () => loadOverview(),
);
</script>

<template>
    <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
        <h2 class="flex items-center gap-2 text-base font-semibold">
            <LaravelLogo size="sm" />
            Laravel Toolkit
        </h2>

        <template v-if="!attached">
            <p class="mt-1 text-sm text-zinc-500">
                {{ t('toolkit.create_help', { infra: domain.infra_slug }) }}
            </p>
            <p v-if="attachError" class="mt-2 text-sm text-red-600">{{ attachError }}</p>
            <form class="mt-5" @submit.prevent="emit('attach')">
                <button
                    type="submit"
                    class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                    :disabled="attachProcessing || !canMutateHosting"
                >
                    {{ t('toolkit.create_button') }}
                </button>
            </form>
        </template>

        <template v-else>
            <p class="mt-1 text-sm text-zinc-500">
                {{ t('toolkit.attached_help', { infra: domain.infra_slug }) }}
            </p>

            <nav class="-mx-1 mt-5 flex min-w-0 flex-wrap gap-1 border-b border-neutral-200" aria-label="Laravel Toolkit">
                <button
                    v-for="section in sections"
                    :key="section.id"
                    type="button"
                    class="border-b-2 px-3 py-2 text-sm font-medium"
                    :class="sectionClass(section.id)"
                    @click="currentSection = section.id"
                >
                    {{ section.label }}
                </button>
            </nav>

            <div v-if="overviewLoading" class="mt-5 text-sm text-zinc-500">{{ t('toolkit.loading') }}</div>
            <p v-else-if="overviewError" class="mt-5 text-sm text-red-600">{{ overviewError }}</p>

            <div v-show="currentSection === 'panoramica'" class="mt-5 space-y-4">
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-zinc-50 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">APP_URL</dt>
                        <dd class="mt-1">
                            <a
                                :href="overview?.app_url || `https://${domain.fqdn}`"
                                target="_blank"
                                rel="noopener"
                                class="text-sm font-medium break-all underline underline-offset-2"
                            >
                                {{ overview?.app_url || `https://${domain.fqdn}` }}
                            </a>
                        </dd>
                    </div>
                    <div class="rounded-lg bg-zinc-50 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ t('common.stack') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ domain.infra_slug }}</dd>
                    </div>
                    <div class="rounded-lg bg-zinc-50 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ t('toolkit.app_status') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ overview?.application_status || '—' }}</dd>
                    </div>
                    <div class="rounded-lg bg-zinc-50 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ t('toolkit.last_deploy') }}</dt>
                        <dd class="mt-1 text-sm font-medium">
                            {{ overview?.last_deploy_title || '—' }}
                            <span v-if="overview?.last_deploy_status" class="text-zinc-500">
                                · {{ overview.last_deploy_status }}
                            </span>
                        </dd>
                    </div>
                    <div class="rounded-lg bg-zinc-50 px-3 py-2 sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ t('common.repository') }}</dt>
                        <dd class="mt-1 text-sm">
                            <a
                                v-if="canOpenDokploy && dokployUrl"
                                :href="dokployUrl"
                                target="_blank"
                                rel="noopener"
                                class="font-medium underline underline-offset-2"
                            >
                                {{ t('toolkit.configure_dokploy') }}
                            </a>
                            <span v-else class="text-zinc-500">{{ t('toolkit.configure_dokploy') }}</span>
                            <span v-if="overview?.git_configured" class="ml-2 text-zinc-500">
                                {{ t('toolkit.source_present') }}
                            </span>
                        </dd>
                    </div>
                </dl>
                <p v-if="overview?.message" class="text-sm text-amber-700">{{ overview.message }}</p>
                <div
                    v-if="showGitLabConnectBanner"
                    class="rounded-lg border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm text-amber-950"
                >
                    <p v-if="catalogHint" class="text-amber-900">{{ catalogHint }}</p>
                    <p v-else-if="dokployGitLabAvailable" class="text-amber-900">
                        {{ t('toolkit.gitlab_oauth') }}
                    </p>
                    <p v-else class="text-amber-900">
                        {{ t('toolkit.gitlab_missing') }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-if="dokployGitLabAvailable"
                            type="button"
                            class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                            :disabled="gitlabConnectLoading"
                            @click="refreshToolkitFromDokployGitLab"
                        >
                            {{ gitlabConnectLoading ? t('common.reading') : t('toolkit.use_dokploy_gitlab') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-zinc-300 bg-white px-3.5 py-2 text-sm font-medium hover:bg-zinc-50"
                            @click="openGitLabModal"
                        >
                            {{ t('toolkit.group_token') }}
                        </button>
                    </div>
                    <p v-if="gitlabConnectError" class="mt-2 text-sm text-red-700">{{ gitlabConnectError }}</p>
                    <p v-if="gitlabConnectSuccess" class="mt-2 text-sm text-emerald-800">{{ gitlabConnectSuccess }}</p>
                </div>
                <p
                    v-else-if="catalogHint"
                    class="text-sm"
                    :class="catalogFromGit ? 'text-emerald-800' : 'text-zinc-600'"
                >
                    {{ catalogHint }}
                </p>
            </div>

            <div v-show="currentSection === 'artisan'" class="mt-5 space-y-4">
                <p v-if="!ready" class="text-sm text-amber-700">{{ disabledHint }}</p>
                <p v-else-if="terminalWorkflow" class="text-sm text-zinc-700">{{ overview?.exec_message }}</p>
                <a
                    v-if="canOpenDokploy && terminalUrl && terminalWorkflow"
                    :href="terminalUrl"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800"
                >
                    {{ t('toolkit.open_terminal') }}
                </a>
                <p class="text-sm text-zinc-500">
                    <template v-if="catalogFromGit">
                        {{ t('toolkit.artisan_git') }}
                    </template>
                    <template v-else>
                        {{ t('toolkit.artisan_fallback') }}
                    </template>
                </p>
                <p v-if="catalogHint" class="text-sm" :class="catalogFromGit ? 'text-emerald-800' : 'text-amber-800'">
                    {{ catalogHint }}
                </p>
                <div
                    v-for="category in commandCatalog.artisan"
                    :key="category.id"
                    class="space-y-2 rounded-lg border border-neutral-100 bg-zinc-50/80 p-3"
                >
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ category.label }}</h3>
                    <div class="flex flex-wrap gap-2">
                        <template v-for="preset in category.commands" :key="preset.command">
                            <button
                                v-if="preset.exec !== false"
                                type="button"
                                class="rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium hover:bg-zinc-50 disabled:opacity-50"
                                :disabled="!canRunCommands || running === 'artisan'"
                                @click="runCommand('artisan', preset.command)"
                            >
                                {{
                                    terminalWorkflow
                                        ? `${preset.label} · ${t('common.copy_and_terminal')}`
                                        : preset.label
                                }}
                            </button>
                            <button
                                v-else
                                type="button"
                                class="rounded-lg border border-dashed border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium hover:bg-zinc-50"
                                :disabled="!ready"
                                @click="copyArtisanCommand(preset.command)"
                            >
                                {{ preset.label }} · {{ t('common.copy_suffix') }}
                            </button>
                        </template>
                    </div>
                </div>
                <form class="grid gap-3 sm:grid-cols-[1fr_auto]" @submit.prevent="runCommand('artisan', artisanCommand)">
                    <input
                        v-model="artisanCommand"
                        type="text"
                        :placeholder="t('toolkit.placeholder_artisan')"
                        class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                        :disabled="!canRunCommands"
                    />
                    <button
                        type="submit"
                        class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="!canRunCommands || running === 'artisan'"
                    >
                        {{ terminalWorkflow ? t('common.copy_and_open_terminal') : t('common.run') }}
                    </button>
                </form>
                <p v-if="artisanError" class="text-sm text-red-600">{{ artisanError }}</p>
                <pre
                    v-if="artisanOutput"
                    class="overflow-x-auto rounded-lg bg-zinc-950 p-3 text-xs whitespace-pre-wrap text-zinc-100"
                    >{{ artisanOutput }}</pre
                >
            </div>

            <div v-show="currentSection === 'composer'" class="mt-5 space-y-4">
                <p v-if="!ready" class="text-sm text-amber-700">{{ disabledHint }}</p>
                <p v-else-if="terminalWorkflow" class="text-sm text-zinc-700">{{ overview?.exec_message }}</p>
                <a
                    v-if="canOpenDokploy && terminalUrl && terminalWorkflow"
                    :href="terminalUrl"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800"
                >
                    {{ t('toolkit.open_terminal') }}
                </a>
                <p class="text-sm text-zinc-500">
                    {{ t('toolkit.composer_allow') }}
                </p>
                <div
                    v-for="category in commandCatalog.composer"
                    :key="category.id"
                    class="space-y-2 rounded-lg border border-neutral-100 bg-zinc-50/80 p-3"
                >
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ category.label }}</h3>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="preset in category.commands"
                            :key="preset.command"
                            type="button"
                            class="rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium hover:bg-zinc-50 disabled:opacity-50"
                            :disabled="!canRunCommands || running === 'composer'"
                            @click="runCommand('composer', preset.command)"
                        >
                            {{
                                terminalWorkflow
                                    ? `${preset.label} · ${t('common.copy_and_terminal')}`
                                    : preset.label
                            }}
                        </button>
                    </div>
                </div>
                <form class="grid gap-3 sm:grid-cols-[1fr_auto]" @submit.prevent="runCommand('composer', composerCommand)">
                    <input
                        v-model="composerCommand"
                        type="text"
                        :placeholder="t('toolkit.placeholder_composer')"
                        class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                        :disabled="!canRunCommands"
                    />
                    <button
                        type="submit"
                        class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="!canRunCommands || running === 'composer'"
                    >
                        {{ terminalWorkflow ? t('common.copy_and_open_terminal') : t('common.run') }}
                    </button>
                </form>
                <p v-if="composerError" class="text-sm text-red-600">{{ composerError }}</p>
                <pre
                    v-if="composerOutput"
                    class="overflow-x-auto rounded-lg bg-zinc-950 p-3 text-xs whitespace-pre-wrap text-zinc-100"
                    >{{ composerOutput }}</pre
                >
            </div>

            <div v-show="currentSection === 'node'" class="mt-5 space-y-4">
                <p v-if="!ready" class="text-sm text-amber-700">{{ disabledHint }}</p>
                <p v-else-if="terminalWorkflow" class="text-sm text-zinc-700">{{ overview?.exec_message }}</p>
                <p class="text-sm text-zinc-500">
                    <template v-if="catalogFromGit">
                        {{ t('toolkit.npm_git') }}
                    </template>
                    <template v-else>
                        {{ t('toolkit.npm_fallback') }}
                    </template>
                </p>
                <p v-if="overview?.build_type" class="text-sm">
                    {{ t('toolkit.build_type') }} <span class="font-medium">{{ overview.build_type }}</span>
                </p>
                <div
                    v-for="category in commandCatalog.npm"
                    :key="category.id"
                    class="space-y-2 rounded-lg border border-neutral-100 bg-zinc-50/80 p-3"
                >
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ category.label }}</h3>
                    <div class="flex flex-wrap gap-2">
                        <template v-for="preset in category.commands" :key="preset.command">
                            <button
                                v-if="preset.exec !== false"
                                type="button"
                                class="rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium hover:bg-zinc-50 disabled:opacity-50"
                                :disabled="!canRunCommands || running === 'npm'"
                                @click="runCommand('npm', preset.command)"
                            >
                                {{
                                    terminalWorkflow
                                        ? `${preset.label} · ${t('common.copy_and_terminal')}`
                                        : preset.label
                                }}
                            </button>
                            <button
                                v-else
                                type="button"
                                class="rounded-lg border border-dashed border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium hover:bg-zinc-50"
                                :disabled="!ready"
                                @click="copyNpmCommand(preset.command)"
                            >
                                {{ preset.label }} · {{ t('common.copy_suffix') }}
                            </button>
                        </template>
                    </div>
                </div>
                <form class="grid gap-3 sm:grid-cols-[1fr_auto]" @submit.prevent="runCommand('npm', npmCommand)">
                    <input
                        v-model="npmCommand"
                        type="text"
                        :placeholder="t('toolkit.placeholder_npm')"
                        class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                        :disabled="!canRunCommands"
                    />
                    <button
                        type="submit"
                        class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="!canRunCommands || running === 'npm'"
                    >
                        {{ terminalWorkflow ? t('common.copy_and_open_terminal') : t('common.run') }}
                    </button>
                </form>
                <p v-if="npmError" class="text-sm text-red-600">{{ npmError }}</p>
                <pre
                    v-if="npmOutput"
                    class="overflow-x-auto rounded-lg bg-zinc-950 p-3 text-xs whitespace-pre-wrap text-zinc-100"
                    >{{ npmOutput }}</pre
                >
                <a
                    v-if="terminalUrl"
                    :href="terminalUrl"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex rounded-lg border border-zinc-200 bg-white px-3.5 py-2 text-sm font-medium hover:bg-zinc-50"
                >
                    {{ t('toolkit.terminal_on_dokploy') }}
                </a>
            </div>

            <div v-show="currentSection === 'deploy'" class="mt-5 space-y-4">
                <p class="text-sm text-zinc-500">
                    {{ t('toolkit.deploy_help') }}
                </p>
                <p class="text-sm text-amber-800">
                    {{ t('toolkit.no_config_cache') }}
                </p>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-lg border border-neutral-200 bg-zinc-50 p-4">
                        <h3 class="text-sm font-semibold">{{ t('toolkit.build_heading') }}</h3>
                        <p class="mt-1 text-sm text-zinc-600">
                            {{ t('toolkit.build_help', { type: overview?.build_type || 'nixpacks' }) }}
                        </p>
                        <pre
                            class="mt-3 overflow-x-auto rounded-lg bg-zinc-950 p-3 text-xs whitespace-pre-wrap text-zinc-100"
                            >{{ nixpacksBuildEnv }}</pre
                        >
                        <button
                            type="button"
                            class="mt-2 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium hover:bg-zinc-50"
                            @click="copySnippet('build', nixpacksBuildEnv)"
                        >
                            {{ copied === 'build' ? t('common.copied') : t('toolkit.copy_nixpacks') }}
                        </button>
                    </div>

                    <div class="rounded-lg border border-neutral-200 bg-zinc-50 p-4">
                        <h3 class="text-sm font-semibold">{{ t('toolkit.start_heading') }}</h3>
                        <p class="mt-1 text-sm text-zinc-600">
                            {{ t('toolkit.start_help') }}
                        </p>
                        <pre
                            class="mt-3 overflow-x-auto rounded-lg bg-zinc-950 p-3 text-xs whitespace-pre-wrap text-zinc-100"
                            >{{ startCommandOneLiner }}</pre
                        >
                        <button
                            type="button"
                            class="mt-2 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium hover:bg-zinc-50"
                            @click="copySnippet('oneliner', startCommandOneLiner)"
                        >
                            {{ copied === 'oneliner' ? t('common.copied') : t('toolkit.copy_start') }}
                        </button>
                    </div>
                </div>

                <p v-if="page.props.flash?.success" class="text-sm text-emerald-700">
                    {{ page.props.flash.success }}
                </p>
                <p v-if="deployForm.errors.laravel_deploy" class="text-sm text-red-600">
                    {{ deployForm.errors.laravel_deploy }}
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="deployForm.processing || !attached || !canMutateHosting"
                        @click="applyDeployConfig"
                    >
                        {{ t('toolkit.apply_build') }}
                    </button>
                    <a
                        v-if="canOpenDokploy && dokployUrl"
                        :href="dokployUrl"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex rounded-lg border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium hover:bg-zinc-50"
                    >
                        {{ t('toolkit.open_on_dokploy') }}
                    </a>
                    <button
                        type="button"
                        class="rounded-lg border border-zinc-200 bg-white px-3.5 py-2 text-sm font-medium hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50"
                        disabled
                        :title="recipeHint"
                    >
                        {{ t('toolkit.run_recipe') }}
                    </button>
                </div>
                <p class="text-sm text-zinc-500">
                    {{ t('toolkit.recipe_help') }}
                </p>
            </div>

            <div v-show="currentSection === 'schedulazioni'" class="mt-5 space-y-4">
                <p class="text-sm text-zinc-500">
                    {{ t('toolkit.schedule_help') }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="!canRunCommands || running === 'artisan'"
                        @click="runCommand('artisan', 'schedule:list')"
                    >
                        {{
                            terminalWorkflow
                                ? `schedule:list · ${t('common.copy_and_terminal')}`
                                : t('toolkit.schedule_button')
                        }}
                    </button>
                    <a
                        v-if="terminalUrl"
                        :href="terminalUrl"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex rounded-lg border border-zinc-200 bg-white px-3.5 py-2 text-sm font-medium hover:bg-zinc-50"
                    >
                        {{ t('toolkit.schedule_terminal') }}
                    </a>
                </div>
                <p v-if="!ready" class="text-sm text-amber-700">{{ disabledHint }}</p>
                <p v-else-if="terminalWorkflow" class="text-sm text-zinc-700">{{ overview?.exec_message }}</p>
                <p v-if="artisanError && currentSection === 'schedulazioni'" class="text-sm text-red-600">
                    {{ artisanError }}
                </p>
                <pre
                    v-if="artisanOutput && currentSection === 'schedulazioni'"
                    class="overflow-x-auto rounded-lg bg-zinc-950 p-3 text-xs whitespace-pre-wrap text-zinc-100"
                    >{{ artisanOutput }}</pre
                >
            </div>

            <div v-show="currentSection === 'code'" class="mt-5 space-y-4">
                <p class="text-sm text-zinc-500">
                    {{ t('toolkit.queue_help') }}
                </p>
                <button
                    type="button"
                    class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                    :disabled="!canRunCommands || running === 'artisan'"
                    @click="runCommand('artisan', 'queue:restart')"
                >
                    {{ terminalWorkflow ? `queue:restart · ${t('common.copy_and_terminal')}` : 'queue:restart' }}
                </button>
                <p v-if="!ready" class="text-sm text-amber-700">{{ disabledHint }}</p>
                <p v-else-if="terminalWorkflow" class="text-sm text-zinc-700">{{ overview?.exec_message }}</p>
                <p v-if="artisanError && currentSection === 'code'" class="text-sm text-red-600">{{ artisanError }}</p>
                <pre
                    v-if="artisanOutput && currentSection === 'code'"
                    class="overflow-x-auto rounded-lg bg-zinc-950 p-3 text-xs whitespace-pre-wrap text-zinc-100"
                    >{{ artisanOutput }}</pre
                >
            </div>

            <div v-show="currentSection === 'log'" class="mt-5 space-y-4">
                <p class="text-sm text-zinc-500">
                    {{ t('toolkit.logs_help') }}
                </p>
                <a
                    v-if="canOpenDokploy && dokployUrl"
                    :href="dokployUrl"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800"
                >
                    {{ t('toolkit.open_logs') }}
                </a>
                <p v-else class="text-sm text-amber-700">
                    {{ t('toolkit.logs_missing') }}
                </p>
            </div>
        </template>

        <div
            v-if="showGitLabModal"
            class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/40 p-4"
            @click.self="closeGitLabModal"
        >
            <div class="w-full max-w-lg rounded-xl border border-neutral-200 bg-white p-6 shadow-lg">
                <h3 class="text-base font-semibold text-zinc-900">{{ t('toolkit.group_token') }}</h3>
                <p class="mt-2 text-sm text-zinc-600">
                    {{ t('toolkit.token_help') }}
                </p>
                <form class="mt-5 space-y-4" @submit.prevent="submitGitLabCredentials">
                    <div>
                        <label class="block text-sm font-medium" for="gitlab-connect-url">{{ t('toolkit.gitlab_url') }}</label>
                        <input
                            id="gitlab-connect-url"
                            v-model="gitlabConnectUrl"
                            type="url"
                            required
                            autocomplete="off"
                            class="mt-1.5 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium" for="gitlab-connect-token">{{ t('toolkit.pat') }}</label>
                        <input
                            id="gitlab-connect-token"
                            v-model="gitlabConnectToken"
                            type="password"
                            required
                            autocomplete="off"
                            class="mt-1.5 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                        />
                    </div>
                    <p v-if="gitlabConnectError" class="text-sm text-red-600">{{ gitlabConnectError }}</p>
                    <p v-if="gitlabConnectSuccess" class="text-sm text-emerald-800">{{ gitlabConnectSuccess }}</p>
                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium hover:bg-zinc-50"
                            :disabled="gitlabConnectLoading"
                            @click="closeGitLabModal"
                        >
                            {{ t('common.cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                            :disabled="gitlabConnectLoading"
                        >
                            {{ gitlabConnectLoading ? t('toolkit.verify') : t('toolkit.sync') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>
