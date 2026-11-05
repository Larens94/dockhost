<!-- DomainSftpFileBrowser.vue — Plesk-like jail file browser for domain SFTP tab.
  exports: default
  used_by: resources/js/Pages/Domains/Show.vue
  agent: composer-2.5-fast | cursor | 2026-11-05 | s_sftp_files
-->
<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { usePanelTranslations } from '../composables/usePanelTranslations';

const props = defineProps({
    domainId: {
        type: Number,
        required: true,
    },
    sftpUser: {
        type: Object,
        required: true,
    },
    canMutate: {
        type: Boolean,
        default: false,
    },
});

const { t } = usePanelTranslations();

const currentPath = ref('');
const entries = ref([]);
const loading = ref(false);
const error = ref('');
const newFolderName = ref('');
const uploadInput = ref(null);
const busy = ref(false);

const breadcrumbs = computed(() => {
    const parts = currentPath.value ? currentPath.value.split('/') : [];

    return [{ label: t('domains.show.sftp_files_root'), path: '' }, ...parts.map((part, index) => ({
        label: part,
        path: parts.slice(0, index + 1).join('/'),
    }))];
});

const csrfHeaders = (extra = {}) => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    const token = match ? decodeURIComponent(match[1]) : '';

    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': token,
        ...extra,
    };
};

const filesBase = () => `/domains/${props.domainId}/sftp-users/${props.sftpUser.id}/files`;

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const query = currentPath.value ? `?path=${encodeURIComponent(currentPath.value)}` : '';
        const response = await fetch(`${filesBase()}${query}`, {
            headers: csrfHeaders(),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('list failed');
        }

        const data = await response.json();
        currentPath.value = data.path ?? currentPath.value;
        entries.value = Array.isArray(data.entries) ? data.entries : [];
    } catch {
        error.value = t('domains.show.sftp_files_list_error');
        entries.value = [];
    } finally {
        loading.value = false;
    }
};

const openDir = (name) => {
    currentPath.value = currentPath.value ? `${currentPath.value}/${name}` : name;
};

const goTo = (path) => {
    currentPath.value = path;
};

watch(currentPath, () => {
    load();
});

watch(
    () => props.sftpUser.id,
    () => {
        currentPath.value = '';
        load();
    },
);

onMounted(() => {
    load();
});

const mkdir = async () => {
    const name = newFolderName.value.trim().replace(/[/\\]+/g, '');

    if (!name || name === '.' || name === '..') {
        return;
    }

    const path = currentPath.value ? `${currentPath.value}/${name}` : name;
    busy.value = true;
    error.value = '';

    try {
        const response = await fetch(`${filesBase()}/mkdir`, {
            method: 'POST',
            headers: csrfHeaders({ 'Content-Type': 'application/json' }),
            credentials: 'same-origin',
            body: JSON.stringify({ path }),
        });

        if (!response.ok) {
            throw new Error('mkdir failed');
        }

        newFolderName.value = '';
        await load();
    } catch {
        error.value = t('domains.show.sftp_files_mkdir_error');
    } finally {
        busy.value = false;
    }
};

const uploadFiles = async (event) => {
    const files = event.target.files;

    if (!files?.length) {
        return;
    }

    busy.value = true;
    error.value = '';

    try {
        for (const file of files) {
            const form = new FormData();
            form.append('file', file);
            form.append('path', currentPath.value);

            const response = await fetch(`${filesBase()}/upload`, {
                method: 'POST',
                headers: csrfHeaders(),
                credentials: 'same-origin',
                body: form,
            });

            if (!response.ok) {
                throw new Error('upload failed');
            }
        }

        await load();
    } catch {
        error.value = t('domains.show.sftp_files_upload_error');
    } finally {
        busy.value = false;
        if (uploadInput.value) {
            uploadInput.value.value = '';
        }
    }
};

const removeEntry = async (entry) => {
    const rel = currentPath.value ? `${currentPath.value}/${entry.name}` : entry.name;

    if (!window.confirm(t('domains.show.sftp_files_delete_confirm', { name: entry.name }))) {
        return;
    }

    busy.value = true;
    error.value = '';

    try {
        const response = await fetch(filesBase(), {
            method: 'DELETE',
            headers: csrfHeaders({ 'Content-Type': 'application/json' }),
            credentials: 'same-origin',
            body: JSON.stringify({ path: rel }),
        });

        if (!response.ok) {
            throw new Error('delete failed');
        }

        await load();
    } catch {
        error.value = t('domains.show.sftp_files_delete_error');
    } finally {
        busy.value = false;
    }
};

const downloadUrl = (entry) => {
    const rel = currentPath.value ? `${currentPath.value}/${entry.name}` : entry.name;

    return `${filesBase()}/download?path=${encodeURIComponent(rel)}`;
};

const formatSize = (bytes) => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
</script>

<template>
    <div class="mt-6 rounded-lg border border-zinc-200 bg-white">
        <div class="border-b border-zinc-100 px-4 py-3">
            <h3 class="text-sm font-semibold text-zinc-900">{{ t('domains.show.sftp_files_title') }}</h3>
            <p class="mt-1 text-xs text-zinc-500">{{ t('domains.show.sftp_files_help') }}</p>
            <p class="mt-1 font-mono text-xs text-zinc-600">/{{ currentPath || '' }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-b border-zinc-100 px-4 py-2 text-xs">
            <button
                v-for="(crumb, index) in breadcrumbs"
                :key="crumb.path + String(index)"
                type="button"
                class="text-emerald-800 hover:underline"
                @click="goTo(crumb.path)"
            >
                {{ crumb.label }}
            </button>
        </div>

        <div v-if="canMutate" class="flex flex-wrap items-end gap-2 border-b border-zinc-100 px-4 py-3">
            <div>
                <label class="block text-xs font-medium text-zinc-600">{{ t('domains.show.sftp_files_new_folder') }}</label>
                <input
                    v-model="newFolderName"
                    type="text"
                    class="mt-1 rounded-md border border-zinc-300 px-2 py-1 text-sm"
                    :disabled="busy"
                />
            </div>
            <button
                type="button"
                class="rounded-md bg-zinc-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-900 disabled:opacity-50"
                :disabled="busy || !newFolderName.trim()"
                @click="mkdir"
            >
                {{ t('domains.show.sftp_files_mkdir') }}
            </button>
            <div>
                <input ref="uploadInput" type="file" multiple class="text-xs" :disabled="busy" @change="uploadFiles" />
            </div>
        </div>

        <p v-if="error" class="px-4 py-2 text-sm text-red-600">{{ error }}</p>
        <p v-if="loading" class="px-4 py-3 text-sm text-zinc-500">{{ t('domains.show.sftp_files_loading') }}</p>

        <table v-else class="min-w-full text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500">
                <tr>
                    <th class="px-4 py-2">{{ t('common.name') }}</th>
                    <th class="px-4 py-2">{{ t('domains.show.sftp_files_size') }}</th>
                    <th class="px-4 py-2 text-right">{{ t('common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!entries.length">
                    <td colspan="3" class="px-4 py-4 text-zinc-500">{{ t('domains.show.sftp_files_empty') }}</td>
                </tr>
                <tr v-for="entry in entries" :key="entry.name" class="border-t border-zinc-100">
                    <td class="px-4 py-2">
                        <button
                            v-if="entry.type === 'dir'"
                            type="button"
                            class="font-medium text-emerald-800 hover:underline"
                            @click="openDir(entry.name)"
                        >
                            {{ entry.name }}/
                        </button>
                        <span v-else>{{ entry.name }}</span>
                    </td>
                    <td class="px-4 py-2 text-zinc-600">
                        {{ entry.type === 'dir' ? '—' : formatSize(entry.size) }}
                    </td>
                    <td class="px-4 py-2 text-right">
                        <a
                            v-if="entry.type === 'file'"
                            :href="downloadUrl(entry)"
                            class="mr-2 text-xs text-emerald-800 hover:underline"
                        >
                            {{ t('domains.show.sftp_files_download') }}
                        </a>
                        <button
                            v-if="canMutate"
                            type="button"
                            class="text-xs text-red-700 hover:underline"
                            :disabled="busy"
                            @click="removeEntry(entry)"
                        >
                            {{ t('common.delete') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
